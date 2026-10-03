---
name: laravel-audit
description: >
  Perform a deep, structured, evidence-based audit of a Laravel application
  and produce actionable findings backed by concrete project evidence. Use when
  asked to audit, review, assess, or evaluate an existing Laravel codebase.
metadata:
  agent: any
---

# Laravel Audit

Perform a deep, structured, evidence-based audit of the current Laravel application. Your goal is **trustworthy findings backed by concrete evidence**, not a long list of style nitpicks.

## When to use this skill

Use this skill when asked to audit, review, assess, or evaluate an existing Laravel application for security, performance, architecture, database, testing, or Laravel conventions issues.

For a bounded data-structure / ownership / organizing-model pass (inventory every subsystem, fan out read-only workers, validate and rank P0–P3), use the `laravel-audit-dsa` skill instead.

## Core principles

- **Evidence-first.** Every meaningful finding must cite concrete, verifiable project context: a file path, line/range, symbol, route, migration, query, configuration key, or dependency.
- **Deterministic facts over guesses.** Prefer programmatically gathered facts (Artisan/MCP context tools) over reading raw text and inferring.
- **No invented behavior.** Never claim a package or framework behaves a certain way without verifying it. When uncertain, say the evidence is incomplete.
- **Confirmed vs. hypothesis.** Label findings by confidence: `confirmed` only when evidence fully supports it; otherwise `high`, `medium`, or `low`.
- **Trustworthiness over volume.** A few high-quality findings beat dozens of speculative ones.
- **Read-only.** Never modify application code during the audit.
- **No severity inflation.** Style preferences are never high severity. "Package is old" is not a finding by itself.

## Audit workflow

### Phase A: Discover

Call `review_scope` before any other context tool.

- A non-empty `scope` is the default audit. Read those files and stop there, unless the user asked to audit the whole application. `changed` files are the uncommitted work. `related` files are the view, test, or class that work directly uses, so a finding in the companion view or test still belongs in the review. On this default audit, do not inventory the rest of the application, and do not dump full `routes`, `models`, or `database_schema`. Use a filtered context tool only for a symbol you found in the scope.
- An empty `changed` list means the working tree is clean. Say so. Continue into a whole-application audit only when the user asked for the whole application.
- `available: false` means git cannot tell you what changed. Say the reason. Do not treat it as a clean tree, and do not start a whole-application audit unless the user asked for one.
- `truncated: true` means the file list was capped. Say that the scope is partial.

When the user asked for a whole-application audit, gather deterministic project facts. When Artisan is available, start with `php artisan auditor:status`, `php artisan auditor:context --list`, and `php artisan auditor:rules --applicable`. Then run the Laravel Auditor context tools (MCP) or inspect:

- `project_info`: Laravel version, PHP version, database engine, ecosystem packages, architecture signals.
- `routes`: registered routes and their handlers.
- `models`: models, tables, fillable/guarded, casts, relationships.
- `database_schema`: tables, columns, types, indexes, and foreign keys (read-only).
- `dependencies`: composer packages and versions.
- `configuration`: config keys and safe values.
- `policies_authorization`: gates, policies, middleware.
- `jobs_events_schedules`: jobs, events/listeners, schedules.
- `tests`: test framework and coverage signals.
- `migrations`: migration files.
- `subsystems`: ownership-bounded inventory for a DSA-style coordinator audit.
- `changed_files`: uncommitted files (staged, unstaged, untracked).
- `review_scope`: the audit boundary for that work. `changed` is the dirty files. `related` is the view, test, or class those files directly use. `scope` is the union, and it is the only set of files to read.

Four tools accept optional read-only filters for focused verification: `routes` (`uri`, `name`, `action`, `method`), `models` (`class`, `table`), `database_schema` (`table`), and `dependencies` (`package`). Filtered responses report `total_count` so you always know how much of the full inventory was returned; call without arguments for the complete payload.

Fall back to `composer.json`, `bootstrap/app.php`, `config/app.php`, and the file tree when tools are unavailable.

Record the application type (web, API, admin panel, package) and any ecosystem packages (Livewire, Filament, Inertia, Pest, Sanctum, Horizon, etc.) — these determine which rules apply.

On a whole-application audit, build a **feature inventory** from the route surface and UI entry points before scoping. List each feature (login, checkout, admin dashboard, etc.), the routes and views that implement it, and the model/service behind it. This inventory drives testing and authorization coverage later, and surfaces stubbed or hallucinated features (a route pointing at a missing controller, an empty view, a TODO handler) early. A `review_scope` audit inventories only the features touched by `scope`.

### Phase B: Scope

Select only the audit domains relevant to this application. Do not blindly run every check. Reason about which domains matter and state the scope before investigating.

Default domains: `security`, `performance`, `architecture`, `database`, `testing`, `conventions`. Skip domains that are clearly irrelevant (e.g. skip queue analysis when the app has no jobs or queue driver).

`review_scope` is the default boundary. `changed_files` is the raw uncommitted list without the related view, test, or class. Use `changed_files` only when you need that raw list. A clean working tree returning zero files is a valid result, not a collection failure. Widen a non-empty `review_scope` to the whole application only when the user asked for that.

When the user asks to gate a pull request or CI, do not use `changed_files` or `--dirty` as the scope. Those see the working tree, which a CI checkout leaves clean. Use `--base=origin/main` (or the pull request's base ref) on `auditor:ci`. The ref must exist locally.

### Phase C: Investigate

For each selected domain:

1. Collect relevant project facts using the context tools. In a `review_scope` audit the facts are the files in `scope`; do not open a full collector.
2. Inspect source code around the facts.
3. Trace important behavior across files already in `scope` (controller → service → model → route → view). Do not follow a symbol into a file outside `scope`.
4. Cross-check evidence across multiple sources.
5. Separate confirmed problems from hypotheses.

The rule reference (`php artisan auditor:rules`, or `--applicable` / `--domain=`) contains the rule IDs, severity, evidence requirements, and false-positive considerations. Use it to map observations to stable rule IDs. Ecosystem packs (Livewire, Filament, Inertia, Sanctum, Pest) only apply when those packages are installed. Queue and DSA rules always apply.

### Phase D: Verify

Before reporting a high-severity finding, attempt to verify it:

- Inspect the route and its middleware.
- Inspect the model/relationship definitions.
- Inspect the schema or migrations.
- Inspect the config for the relevant keys.
- Inspect dependency versions and their docs.
- Inspect the tests.
- Review logs/errors when relevant.
- Run a **safe, read-only** runtime check only when a tool exists (never migrate, seed, refresh, or otherwise mutate data).

If you cannot verify, lower the confidence and say so explicitly.

### Phase E: Report

Write findings to `storage/auditor-findings.json` (an array, or `{ "findings": [ ... ] }`). Each finding must include:

- `id`: unique instance id (e.g. `F-2026-0001`).
- `rule_id`: a stable rule ID (e.g. `AUD-SEC-001`) when one matches.
- `title`: short and specific.
- `domain`: the audit domain.
- `severity`: `critical`, `high`, `medium`, `low`, or `info`.
- `confidence`: `confirmed`, `high`, `medium`, or `low`.
- `status`: `open` for new findings.
- `summary`: what is wrong.
- `why_it_matters`: why it matters for this app.
- `evidence`: concrete references (file paths, lines, routes, symbols). Type each entry so it can be checked automatically: `file`, `migration`, or `test` for paths, and `route`, `config`, `symbol`, `query`, `dependency`, or `log` for everything else. A `file` entry is treated as a path even without an extension; a `route` or `config` entry never is.
- `affected_resources`: files/routes/config involved.
- `recommendation`: what to do about it.
- `remediation`: optional step-by-step guidance.
- `verification_notes`: how you verified (or why you could not).
- `metadata.priority`: `p0`–`p3` when you rank the report. Keep P3 small.
  - **P0** — reachable wrong-record, lost-update, authorization, or durable-state risk
  - **P1** — concrete boundary failures with less immediate damage
  - **P2** — useful invariant improvements with narrower impact
  - **P3** — telemetry / diagnostics / maintainability

Then render. For a `review_scope` audit, render the findings file as it stands. Do not pass `--dirty`: a finding on a related file is about a file that may not itself be dirty, and `--dirty` would drop it.

```bash
php artisan auditor:report --findings=storage/auditor-findings.json
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
```

To gate a pull request, pass `--base` with the base ref. It keeps findings whose evidence or affected resources name a file in the committed diff since the merge base of that ref and `HEAD`. A clean checkout still has those commits, so this is the CI gate:

```bash
php artisan auditor:ci --findings=storage/auditor-findings.json --base=origin/main --fail-on=high
```

If the ref is missing, the command fails and says why. Fetch it (in GitHub Actions, `fetch-depth: 0`). Do not drop `--base` and report the full-repo result as the pull request result. A finding with no file reference stays in scope.

`--dirty` is only the uncommitted working tree. Use it for local edits. On a clean checkout it matches no files. Pass `--base` and `--dirty` together when both the branch commits and the uncommitted edits should count. `--base` alone ignores uncommitted files.

The report includes project facts, domains audited, counts by severity/domain, priority synthesis, and the key risks.

## Anti-patterns to avoid

- Inventing vulnerabilities because a pattern is uncommon.
- Treating every loop or every query as a performance problem.
- Recommending "create a repository for every model" or "move everything into services".
- Equating line coverage with test quality.
- Claiming exploitability without evidence.
- Recommending upgrades solely because a package is old.
- Presenting style preferences as high-severity findings.
- Claiming the audit is complete when evidence was missing.
- Running `migrate`, `db:wipe`, `db:seed`, or any other mutating Artisan command to "verify" schema.
- Treating `composer_audit.available: false` (or an empty advisory list) as “no vulnerabilities” when the collector did not actually run `composer audit`.
- Auditing the whole application when `review_scope` returned a non-empty `scope` and the user did not ask for the whole application.
- Ignoring `related` and reviewing only the dirty file, or wandering past `scope` into unrelated callers.
