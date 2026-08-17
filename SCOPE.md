# Scope

This document defines what Laravel Auditor v0.1.0 includes, what it intentionally defers, and the boundaries it respects.

## What Laravel Auditor is

Laravel Auditor provides an AI coding agent with a repeatable, evidence-based audit methodology for Laravel applications. It supplies deterministic project context, structured findings, a rule catalog, and an orchestration skill. The agent remains the reasoning engine; the package supplies the workflow.

## v0.1.0 scope

### Source

| Area | What is included |
| --- | --- |
| Service provider | Singleton bindings, config merge, publish tags, command registration, Boost MCP registration |
| Context collectors | 11 read-only collectors: project info, routes, models, migrations, database schema, dependencies, configuration, authorization, jobs/events/schedules, tests, subsystems |
| MCP server | stdio MCP server exposing the 11 collectors as read-only tools; automatic Boost integration via `boost.mcp.tools.include` |
| Audit rules | 61 evidence-first rules across 6 core domains (security, performance, architecture, database, testing, conventions) |
| Ecosystem rule packs | Livewire, Filament, Inertia, Sanctum, Pest, queues — applied only when the target package is installed |
| Findings | `Finding`, `FindingCollection`, `FindingLoader`, JSON schemas (`finding.schema.json`, `report.schema.json`), example findings file |
| Reports | Markdown, JSON, CLI text, and SARIF renderers; `auditor:report` and `auditor:ci` commands |
| Artisan commands | `auditor:install`, `auditor:status`, `auditor:rules`, `auditor:report`, `auditor:context`, `auditor:ci`, `auditor:mcp` |
| Installer | Idempotent standalone installer with `--dry-run`, `--force`, `--agents` options; writes agent adapters (AGENTS.md, CLAUDE.md, GEMINI.md, Cursor, Copilot, Codex, Junie, Zed) only when missing |
| Agent resources | 8 skills, 3 guidelines, 2 schemas, 1 example file — published via `vendor:publish` or `auditor:install` |
| Facades | `LaravelAuditor` facade exposing `collect()` |
| Configuration | `config/laravel-auditor.php` with domains, extra rule directories, standalone resource target, agent list, context options |
| Documentation site | Docsmith-based docs deployed to GitHub Pages with OG images, sitemap, and per-page metadata |
| CI | 24-job matrix (Ubuntu + Windows, PHP 8.3/8.4/8.5, Laravel 12/13, prefer-lowest/stable), PHPStan, Pint, type coverage, Pest |

### Testing

| Layer | Coverage |
| --- | --- |
| Unit tests | Collectors, findings, reports, rules, install commands, facets, MCP wiring, context registry |
| Feature tests | Full collector accuracy, rule registry, report rendering, installer idempotency, dry-run, force, agent selection, Boost detection |
| Static analysis | PHPStan level max via Larastan, 100% type coverage, Pint clean |
| CI matrix | 24 combinations across OS, PHP, Laravel, stability |

## What is NOT in scope for v0.1.0

These items are intentionally deferred. They may appear in future releases.

| Area | Reason |
| --- | --- |
| Automatic code fixes | The package is read-only by design; fixes remain the agent's responsibility |
| Historical audit baselines | No diffing between audit runs; findings are point-in-time |
| Web dashboard | No browser UI; all output is CLI, JSON, Markdown, or SARIF |
| Legacy/standalone PHP runner | Requires a Laravel application context; cannot run outside a Laravel app |
| Organization policy packs | No multi-tenant or organization-level rule configuration |
| Deeper ecosystem packs | Beyond Livewire, Filament, Inertia, Sanctum, Pest, queues — no Nova, Vapor, or other ecosystem rules |
| Runtime monitoring | No production telemetry, metrics, or APM integration |
| Autonomous AI product | The package does not run agents autonomously; a human triggers the audit |
| Custom rule authoring API | Rules are PHP files in a directory; no formal plugin API or contract beyond the file format |
| Multi-language support | English only; no translation layer |
| CI/CD gate beyond exit code | `auditor:ci` provides exit codes; no GitHub status checks, PR annotations, or review comments |

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

- **Public API**: The `LaravelAuditor` facade, `auditor:context` command, config file, and MCP tools are the stable public interface. Changes will follow semver.
- **Findings schema**: The `finding.schema.json` and `report.schema.json` define the output contract. Fields may be added but not removed or renamed within v0.x.
- **Rule IDs**: Rule IDs (e.g., `AUD-SEC-001`) are stable within v0.1.0. Rules may be added; existing rules may have severity or confidence adjusted with a changelog entry.
- **Agent resources**: Skills, guidelines, and adapters are published to the user's project. They may be regenerated with `--force` and are not part of the PHP API contract.
- **Internal classes**: Classes not explicitly documented as public API (collectors, renderers, registries) may change between minor versions without notice.

## Release process

1. Ensure all CI checks pass.
2. Update `CHANGELOG.md` with the release date and categorized entries.
3. Tag the release.
4. Create a GitHub Release from the tag.
5. `update-changelog.yml` automatically picks up the new release.
