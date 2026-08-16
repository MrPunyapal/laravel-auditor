---
title: Agent setup
description: Wire Laravel Auditor into Codex, Claude Code, Gemini, Cursor, Copilot, and Laravel Boost.
order: 4
slug: agents
---

Audit knowledge lives once, agent-agnostic, under `resources/auditor`. Adapters stay thin.

## Ask the agent

> Use the laravel-audit skill. Discover this Laravel app, scope the relevant domains, and report only findings with file, route, or schema evidence.

For a data-structure / ownership pass:

> Use the laravel-audit-dsa skill. Inventory subsystems, review them in bounded read-only lanes, then rank P0–P3.

### Full example prompt

> You are auditing the Laravel application in this project using the Laravel Auditor methodology.
>
> 1. Use the laravel-audit skill. Follow its Discover → Scope → Verify → Report workflow.
> 2. Start by calling the context MCP tools to gather deterministic facts BEFORE reading code:
>    - `project_info` — PHP/Laravel versions, database, ecosystem signals
>    - `routes` — the full route surface
>    - `models` — all models with fillable/guarded, casts, relationships
>    - `migrations` — schema changes over time
>    - `database_schema` — actual tables/columns/indexes
>    - `dependencies` — installed packages and versions
>    - `configuration` — config keys in use
>    - `authorization` — gates, policies, auth middleware
>    - `jobs_events_schedules` — queues, events, cron
>    - `tests` — test coverage layout
> 3. Scope the relevant domains (e.g., security, database, architecture, testing). Do NOT audit everything superficially — pick the domains with the most risk signal and go deep.
> 4. For every potential finding, verify against actual files, routes, or schema. Never report a guess.
> 5. Report findings ranked P0–P3, each with: file/route/schema evidence, the rule violated, why it matters, and a concrete fix.
> 6. Be read-only. Do not modify any application code.

For a quick Discover-only pass:

> Start with a Discover phase only: run all 11 context tools, summarize what this app is (framework versions, database, route surface, model list, test coverage), and flag any immediate red flags in 3-5 bullets. Do not write findings yet.

## Standalone adapters

`php artisan auditor:install` asks which AI agent(s) the project uses, then writes pointers only when the file is missing:

- OpenCode: `AGENTS.md` + `.agents/skills`
- Claude Code: `CLAUDE.md` + `.claude/skills`
- Cursor: `.cursor/rules/laravel-auditor.mdc` + `.cursor/skills`
- Copilot: `.github/copilot-instructions.md` + `.github/skills`
- Gemini: `GEMINI.md` + `.gemini/skills` (no MCP)
- Codex: `AGENTS.md` + `.agents/skills` + `.codex/config.toml`
- Junie: `AGENTS.md` + `.junie/skills` + `.junie/mcp/mcp.json`
- Zed: `AGENTS.md` + `.agents/skills` + `.zed/settings.json`

The adapters point at `.ai/skills/laravel-audit` and `.ai/guidelines/core.md`. They do not copy the full audit knowledge into every vendor file.

For the agents that support MCP, the installer also registers the `laravel-auditor` server so tools like `audit`, `context`, and `rules` are available to the agent.

Non-interactive runs (CI, `--no-interaction`) resolve agents from `--agents`, then project detection, then the `laravel-auditor.agents` config, then all supported agents.

## Laravel Boost

When Boost is present, skip the standalone installer and run `boost:install` / `boost:update`.

When Boost is installed, the package also registers its read-only context tools (`project_info`, `routes`, `models`, `migrations`, `database_schema`, `dependencies`, `configuration`, `authorization`, `jobs_events_schedules`, `tests`, `subsystems`) inside Boost's MCP server automatically through `boost.mcp.tools.include`.

Boost skills shipped by this package:

- `laravel-audit` — six-domain evidence-based audit
- `laravel-audit-dsa` — bounded subsystem / DSA audit
- `laravel-audit-security`, `performance`, `architecture`, `database`, `testing`, `conventions`
- `laravel-auditor-development` — install and wire the package

## Read-only

Auditing must not modify application code. Installation may write Auditor-owned resources; it will not overwrite user-owned files without `--force`.
