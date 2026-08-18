# Release Notes

## [Unreleased](https://github.com/mrpunyapal/laravel-auditor/compare/v0.1.2...HEAD)

### Changed

- `context.composer_audit` is now **on by default** so `AUD-DEP-001` can use advisory data. The collector still fails soft when Composer, the network, or `composer.lock` is missing. Set the flag to `false` to skip the shell-out. Test-case listing remains off.

## [v0.1.2](https://github.com/mrpunyapal/laravel-auditor/releases/tag/v0.1.2) - 2026-08-19

Compatibility release. No public command, config key, MCP tool name, or rule ID was removed.

### Fixed

- Standalone and Boost security skills are now identical and cover CSRF, XSS, raw SQL, and dependency advisories
- Guidelines list all 11 context tools, including `subsystems`
- `laravel-audit` now writes findings JSON, ranks `metadata.priority`, and renders via `auditor:report` / `auditor:ci`
- Docs no longer imply `composer audit` ran when the collector is disabled
- Removed the `php artisan migrate` verification step from the audit skill
- OpenCode is detected from `opencode.json` / `opencode.jsonc` only, not a `.agents` directory
- `migrations` also reads workbench and migrator-registered paths
- `configuration` reports file and top-level keys instead of every nested alias/provider index
- `tests` lists test files only, not helpers, fixtures, or snapshots
- MCP and SARIF versions come from the installed Composer package instead of a hardcoded string
- `project_info` architecture signals and source layout follow application/workbench paths instead of only `app_path()`
- `tests` and `subsystems` discover test/migration directories next to the application root

### Added

- Boost guidelines for findings and DSA (`findings.blade.php`, `dsa.blade.php`)
- Standalone `laravel-auditor-development` skill
- Domain skills now load `auditor:rules --applicable` and mention ecosystem packs (Livewire, Filament, Inertia, Sanctum, Pest, queues)
- Compatibility policy: public commands, config keys, MCP tool names, finding fields, and rule IDs stay backward compatible; breaking changes are exceptional

## [v0.1.1](https://github.com/mrpunyapal/laravel-auditor/releases/tag/v0.1.1) - 2026-08-18

### Fixed

- `auditor:install` no longer wires every supported agent when none are configured or detected
- Copilot detection no longer treats a `.github` or `.vscode` directory as proof the agent is in use
- MCP config writes honor `--force` and no longer overwrite an existing `laravel-auditor` entry by default
- Codex TOML MCP registration no longer appends a duplicate block on every install
- `composer audit` and `pest --list-tests` are off by default so MCP and context collection stay local
- `project_info` now reports whether a package is a Composer dev dependency correctly
- `vendor:publish` and `auditor:status` honor `resources_target` instead of assuming `.ai`
- MCP server registrations pass `-q` so Artisan boot output cannot break stdio framing
- Docs "Edit this page" links now point at `md/` sources instead of missing repo-root files

## [v0.1.0](https://github.com/mrpunyapal/laravel-auditor/releases/tag/v0.1.0) - 2026-08-17

Initial release.

### Added

- Evidence-based audit domain model with 6 core domains (security, performance, architecture, database, testing, conventions)
- 61 evidence-first rules across core domains
- Ecosystem rule packs for Livewire, Filament, Inertia, Sanctum, Pest, and queues
- DSA coordinator skill, `subsystems` context tool, and P0–P3 report ranking
- 11 read-only context collectors: project info, routes, models, migrations, database schema, dependencies, configuration, authorization, jobs/events/schedules, tests, subsystems
- Read-only stdio MCP server exposing context collectors as tools
- Automatic registration of context tools inside Laravel Boost's MCP server (`boost.mcp.tools.include`)
- 8 agent skills: laravel-audit, laravel-audit-security, laravel-audit-performance, laravel-audit-architecture, laravel-audit-database, laravel-audit-testing, laravel-audit-conventions, laravel-audit-dsa
- 3 agent guidelines: core, findings, dsa
- Standalone installer (`auditor:install`) with `--dry-run`, `--force`, `--agents` options
- Agent adapters for OpenCode, Claude Code, Cursor, Copilot, Gemini, Codex, Junie, and Zed
- Artisan commands: `auditor:install`, `auditor:status`, `auditor:rules`, `auditor:report`, `auditor:context`, `auditor:ci`, `auditor:mcp`
- `LaravelAuditor` facade with `collect()`, `rules()`, `context()`, `project()` methods
- Finding schema with severity, confidence, evidence, and priority tiers (P0–P3)
- Report renderers: Markdown, JSON, CLI text, SARIF
- CI gating with `auditor:ci` and configurable severity threshold
- JSON schemas for findings and reports (`finding.schema.json`, `report.schema.json`)
- Example findings file for quick testing
- Docsmith documentation site with OG images, sitemap, and per-page metadata
- Configuration file (`config/laravel-auditor.php`) with domains, rules, agents, context options, and report defaults

### Fixed

- ModelsCollector now catches exceptions during model instantiation, preventing one broken model from crashing all collectors
- FindingCollection docblock corrected to accurately describe mutation semantics
