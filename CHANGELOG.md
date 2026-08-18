# Release Notes

## [Unreleased](https://github.com/mrpunyapal/laravel-auditor/compare/v0.1.1...HEAD)

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
