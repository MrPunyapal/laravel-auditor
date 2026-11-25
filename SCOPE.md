# Scope

This document describes Laravel Auditor on the default branch, including changes listed under Unreleased in the changelog.

## What Laravel Auditor is

Laravel Auditor provides an AI coding agent with a repeatable, evidence-based audit methodology for Laravel applications. It supplies deterministic project context, structured findings, a rule catalog, and an orchestration skill. The agent remains the reasoning engine; the package supplies the workflow.

## Current scope

### Source

| Area | What is included |
| --- | --- |
| Service provider | Singleton bindings, config merge, publish tags, command registration, Boost MCP registration |
| Context collectors | 13 read-only collectors: project info, routes, models, migrations, database schema, dependencies, configuration, authorization, jobs/events/schedules, tests, subsystems, changed files, review scope |
| MCP server | stdio MCP server exposing the 13 collectors as read-only tools; automatic Boost integration via `boost.mcp.tools.include` |
| Audit rules | 85 evidence-first rules across 6 core domains (security, performance, architecture, database, testing, conventions), including a deep performance catalog (AUD-PER-008–018) with context-gated, semantic-equivalence-verified optimization rules and an architecture code-smell catalog (AUD-ARC-006–011) |
| Ecosystem rule packs | Livewire, Filament, Inertia, Sanctum, Pest — applied only when the target package is installed. Queue and DSA rules always apply. |
| Findings | `Finding`, `FindingCollection`, `FindingLoader`, JSON schemas (`finding.schema.json`, `report.schema.json`, `findings-baseline.schema.json`), example findings file |
| Reports | Markdown, JSON, CLI text, and SARIF renderers; `auditor:report` and `auditor:ci` commands, GitHub Actions annotations, dependency finding exports, `--base=<ref>` for the committed merge-base diff and `--dirty` for uncommitted files |
| Historical findings baselines | Versioned baseline files, new-finding CI gates, and opt-in severity and reopen checks using status-aware baselines from comparable full audit scopes |
| Unified change review | JSON, Markdown, and text reports combining saved snapshots, optional migration source and driver guidance, and supplied findings. Optional Git association with a local merge base and HEAD. |
| Change review in GitHub Actions | Copyable manual workflow for trusted capture artifacts with separate structural review and findings policy steps |
| Snapshots and migration review | Read-only route and authorization snapshots, optional schema snapshots, snapshot comparisons, structural migration risk indicators, opt-in registered migration source inspection, and conditional database guidance. No production deployment safety guarantee. |
| Artisan commands | `auditor:install`, `auditor:status`, `auditor:rules`, `auditor:report`, `auditor:context`, `auditor:ci`, `auditor:mcp`, `auditor:dependency-findings`, `auditor:snapshot`, `auditor:compare`, `auditor:migration-risk`, `auditor:baseline`, `auditor:review` |
| Installer | Idempotent standalone installer with `--dry-run`, `--force`, `--agents` options; wires only selected, configured, or detected agents; writes adapters only when missing |
| Agent resources | 9 skills (8 audit + setup), 4 guidelines, 4 schemas, and example files |
| Facades | `LaravelAuditor` facade exposing `collect()`, `rules()`, `context()`, and `project()` |
| Configuration | `config/laravel-auditor.php` with domains, extra rule directories, standalone resource target, agent list, custom agents, context options, changed-file options |
| Documentation site | Docsmith-based docs deployed to GitHub Pages with OG images, sitemap, and per-page metadata |
| CI | 24-job matrix (Ubuntu + Windows, Laravel 12 with PHP 8.3–8.5, Laravel 13 with PHP 8.4–8.6, prefer-lowest/stable), PHPStan, Pint, type coverage, Pest |

### Testing

| Layer | Coverage |
| --- | --- |
| Unit tests | Collectors, findings, reports, rules, install commands, facets, MCP wiring, context registry |
| Feature tests | Full collector accuracy, rule registry, report rendering, installer idempotency, dry-run, force, agent selection, Boost detection |
| Static analysis | PHPStan level 7 via Larastan, 100% type coverage, Pint clean |
| CI matrix | 24 combinations across OS, PHP, Laravel, stability |

## What is not currently included

These items are intentionally deferred. They may appear in future releases.

| Area | Reason |
| --- | --- |
| Automatic code fixes | The package is read-only by design; fixes remain the agent's responsibility |
| Historical finding trends | Status-aware baselines support severity and reopen checks for two equivalent audit scopes. Trend tracking is deferred. |
| Web dashboard | No browser UI; all output is CLI, JSON, Markdown, or SARIF |
| Legacy/standalone PHP runner | Requires a Laravel application context; cannot run outside a Laravel app |
| Organization policy packs | No multi-tenant or organization-level rule configuration |
| Deeper ecosystem packs | Beyond Livewire, Filament, Inertia, Sanctum, Pest, and the always-on queue/DSA rules — no Nova, Vapor, or other ecosystem rules |
| Runtime monitoring | No production telemetry, metrics, or APM integration |
| Autonomous AI product | The package does not run agents autonomously; a human triggers the audit |
| Custom rule authoring API | Rules are PHP files in a directory; no formal plugin API or contract beyond the file format |
| Multi-language support | English only; no translation layer |
| Hosted PR review bot | GitHub Actions annotations are supported, but Auditor does not create hosted PR review comments or an external status-check service |

## Architecture boundaries

```
src/
  Audit/          Rules, findings, evidence, domains, report renderers
  Context/        Read-only Laravel collectors used by MCP and reports
  Console/        Artisan commands
  MCP/            stdio MCP server and Boost integration
  Support/        Boost detection, agent utilities, application paths
  Facades/        LaravelAuditor facade
resources/
  auditor/        Agent-agnostic skills, guidelines, rules, schemas, examples
  boost/          Third-party Laravel Boost guidelines and skills
```

- Audit knowledge stays agent-neutral. `AGENTS.md` / `CLAUDE.md` adapters only point at that source of truth.
- Collectors return structured data, not raw source dumps.
- `changed_files` is a scope signal, not a baseline. It reports uncommitted paths only and never diffs two audit runs. `--base` is the committed diff from `git merge-base <ref> HEAD` to `HEAD`. `--dirty` is the working tree. Neither stores a previous audit.
- `review_scope` is the default audit boundary: dirty files plus the view, test, or class they directly use. A whole-application audit runs when the user asks for one.
- The MCP server is read-only; it cannot modify application code.
- Rules reference evidence; they do not invent findings.

## Dependencies

### Runtime

- PHP ^8.3
- illuminate/support ^12.0 || ^13.0

No other runtime dependencies. The package intentionally avoids hard dependencies on Laravel Boost, agent SDKs, or third-party AI services.

### Dev (not shipped)

All development dependencies remain in `require-dev` and are not installed by end users. They include Larastan, Pint, Pest, Testbench, Docsmith, MCP SDK, and agent detection tooling.

## Stability guarantees

This package is installed into other people's apps. **Breaking changes are exceptional.** Prefer additive fixes and new optional config. A break requires a rare major version, a changelog callout, and a migration note.

- **Public API**: The `LaravelAuditor` facade, every `auditor:*` command (names, arguments, options, exit codes), published config keys, and MCP tool names are stable. Do not remove or rename them.
- **Collector / MCP payloads**: Top-level keys and documented fields stay. New keys may be added. Nested noise may be reduced only when the documented fields remain.
- **Findings schema**: `finding.schema.json` and `report.schema.json` are the output contract. Fields may be added but not removed or renamed within v0.x.
- **Rule IDs**: IDs such as `AUD-SEC-001` stay stable. Rules may be added. Severity or confidence of an existing rule may change only with a changelog entry.
- **Config defaults**: Changing a default is a behavior change. Prefer a new opt-in key over flipping an existing default for installed users.
- **Agent resources**: Skills, guidelines, and adapters are published copies. They may be regenerated with `--force` and are not a PHP API, but published command names and finding fields they teach must stay accurate.
- **Internal classes**: Collectors, renderers, and registries may be refactored. Observable command, facade, config, and MCP behavior must not break.

## Release process

1. Ensure all CI checks pass.
2. Update `CHANGELOG.md` with the release date and categorized entries.
3. Tag the release.
4. Create a GitHub Release from the tag.
5. `update-changelog.yml` automatically picks up the new release.
