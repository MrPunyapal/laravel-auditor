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

Gather deterministic project facts first. When available, run the Laravel Auditor context tools (MCP) or inspect:

- `project_info`: Laravel version, PHP version, database engine, ecosystem packages, architecture signals.
- `routes`: registered routes and their handlers.
- `models`: models, tables, fillable/guarded, casts, relationships.
- `database_schema`: tables, columns, types, indexes (read-only).
- `dependencies`: composer packages and versions.
- `configuration`: config keys and safe values.
- `policies_authorization`: gates, policies, middleware.
- `jobs_events_schedules`: jobs, events/listeners, schedules.
- `tests`: test framework and coverage signals.
- `migrations`: migration files.
- `subsystems`: ownership-bounded inventory for a DSA-style coordinator audit.

Fall back to `composer.json`, `bootstrap/app.php`, `config/app.php`, and the file tree when tools are unavailable.

Record the application type (web, API, admin panel, package) and any ecosystem packages (Livewire, Filament, Inertia, Pest, Sanctum, Horizon, etc.) — these determine which rules apply.

Build a **feature inventory** from the route surface and UI entry points before scoping. List each feature (login, checkout, admin dashboard, etc.), the routes and views that implement it, and the model/service behind it. This inventory drives testing and authorization coverage later, and surfaces stubbed or hallucinated features (a route pointing at a missing controller, an empty view, a TODO handler) early.

### Phase B: Scope

Select only the audit domains relevant to this application. Do not blindly run every check. Reason about which domains matter and state the scope before investigating.

Default domains: `security`, `performance`, `architecture`, `database`, `testing`, `conventions`. Skip domains that are clearly irrelevant (e.g. skip queue analysis when the app has no jobs or queue driver).

### Phase C: Investigate

For each selected domain:

1. Collect relevant project facts using the context tools.
2. Inspect source code around the facts.
3. Trace important behavior across files (controller → service → model → route → view).
4. Cross-check evidence across multiple sources.
5. Separate confirmed problems from hypotheses.

The rule reference (list via `php artisan auditor:rules`) contains the rule IDs, severity, evidence requirements, and false-positive considerations. Use it to map observations to stable rule IDs.

### Phase D: Verify

Before reporting a high-severity finding, attempt to verify it:

- Inspect the route and its middleware.
- Inspect the model/relationship definitions.
- Inspect the schema or migrations.
- Inspect the config for the relevant keys.
- Inspect dependency versions and their docs.
- Inspect the tests.
- Review logs/errors when relevant.
- Run a **safe, read-only** runtime check only when a tool exists (never run anything that mutates data).

When migration reliability is in scope, verify the **fresh-start migration path**: on a disposable empty database, run `php artisan migrate` from zero (never against the real database). Confirm the schema matches the migration intent, then discard the disposable database.

If you cannot verify, lower the confidence and say so explicitly.

### Phase E: Report

Produce structured findings. Each finding must include:

- `rule_id`: a stable rule ID (e.g. `AUD-SEC-001`) when one matches.
- `title`: short and specific.
- `domain`: the audit domain.
- `severity`: `critical`, `high`, `medium`, `low`, or `info`.
- `confidence`: `confirmed`, `high`, `medium`, or `low`.
- `summary`: what is wrong.
- `why_it_matters`: why it matters for this app.
- `evidence`: concrete references (file paths, lines, routes, symbols).
- `affected_resources`: files/routes/config involved.
- `recommendation`: what to do about it.
- `remediation`: optional step-by-step guidance.
- `verification_notes`: how you verified (or why you could not).

Then produce a report summary with project facts, domains audited, counts by severity/domain, and the key risks.

## Anti-patterns to avoid

- Inventing vulnerabilities because a pattern is uncommon.
- Treating every loop or every query as a performance problem.
- Recommending "create a repository for every model" or "move everything into services".
- Equating line coverage with test quality.
- Claiming exploitability without evidence.
- Recommending upgrades solely because a package is old.
- Presenting style preferences as high-severity findings.
- Claiming the audit is complete when evidence was missing.
