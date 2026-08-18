---
title: Agent setup
description: Wire Laravel Auditor into Codex, Claude Code, Gemini, Cursor, Copilot, and Laravel Boost.
og_title: Agent setup for Laravel Auditor
og_description: Wire Laravel Auditor into Codex, Claude Code, Gemini, Cursor, Copilot, Junie, Zed, and Laravel Boost.
order: 4
slug: agents
---

Laravel Auditor does not run an audit for you. After install, you open your AI agent and ask it to use the `laravel-audit` skill.

Audit knowledge lives once under `resources/auditor`. Agent-specific files are thin adapters that point the agent at that shared knowledge. The same skills, guidelines, and rules work across every supported agent.

## Integration modes

### Laravel Boost

When Boost is installed, Auditor extends it. Boost consumes Auditor's skills and guidelines directly from `resources/boost/`. The context tools are also registered inside Boost's MCP server automatically through `boost.mcp.tools.include`.

If your project already uses Boost, this is the simplest path. Run `boost:install` or `boost:update` and everything is wired.

### Standalone

When Boost is absent, `auditor:install --agents=claude_code` (or your agent) handles the wiring. It publishes skills, guidelines, and schemas to `.ai/`, writes agent adapter files, and registers the MCP server for agents that support it.

Both paths produce the same audit knowledge — they just deliver it differently.

## Supported agents

The standalone installer supports eight agents:

| Agent | Guidelines | Skills | MCP |
| --- | --- | --- | --- |
| OpenCode | `AGENTS.md` | `.agents/skills` | `opencode.json` |
| Claude Code | `CLAUDE.md` | `.claude/skills` | `.mcp.json` |
| Cursor | `.cursor/rules/laravel-auditor.mdc` | `.cursor/skills` | `.cursor/mcp.json` |
| GitHub Copilot | `.github/copilot-instructions.md` | `.github/skills` | `.vscode/mcp.json` |
| Gemini CLI | `GEMINI.md` | `.gemini/skills` | — |
| Codex | `AGENTS.md` | `.agents/skills` | `.codex/config.toml` |
| Junie | `AGENTS.md` | `.junie/skills` | `.junie/mcp/mcp.json` |
| Zed | `AGENTS.md` | `.agents/skills` | `.zed/settings.json` |

Gemini does not support MCP. All other agents receive MCP registration when the installer runs.

## What an adapter file contains

An adapter is a short file that tells the agent where to find the audit skill and guidelines. For example, the `CLAUDE.md` adapter contains:

```markdown
# Laravel Auditor

This project uses Laravel Auditor for evidence-based Laravel audits.

When asked to audit, review, or assess this application, use the `laravel-audit` skill in `.ai/skills/laravel-audit` and follow `.ai/guidelines/core.md`.

Do not modify application code during an audit. Prefer deterministic project facts from `php artisan auditor:status`, `php artisan auditor:rules`, and the Laravel Auditor MCP tools.
```

The adapter does not contain the full skill, guidelines, or rules. It points at the shared copies in `.ai/`.

## Asking the agent to audit

Once the agent is wired, give it a clear instruction:

> Use the laravel-audit skill to audit this application. Discover the project first, scope the relevant domains, and report only evidenced findings.

### Full example prompt

> You are auditing the Laravel application in this project using the Laravel Auditor methodology.
>
> 1. Use the laravel-audit skill. Follow its Discover -> Scope -> Verify -> Report workflow.
> 2. Start by calling the context MCP tools to gather deterministic facts BEFORE reading code:
>    - `project_info` — PHP/Laravel versions, database, ecosystem signals
>    - `routes` — the full route surface
>    - `models` — all models with fillable/guarded, casts, relationships
>    - `migrations` — schema changes over time
>    - `database_schema` — actual tables/columns/indexes
>    - `dependencies` — installed packages and versions
>    - `configuration` — config keys in use
>    - `policies_authorization` — gates, policies, auth middleware
>    - `jobs_events_schedules` — queues, events, cron
>    - `tests` — test suite: framework, case counts (feature/unit)
>    - `subsystems` — ownership-bounded inventory for a DSA-style coordinator audit
> 3. Scope the relevant domains (e.g., security, database, architecture, testing). Do NOT audit everything superficially — pick the domains with the most risk signal and go deep.
> 4. For every potential finding, verify against actual files, routes, or schema. Never report a guess.
> 5. Report findings ranked P0–P3, each with: file/route/schema evidence, the rule violated, why it matters, and a concrete fix.
> 6. Be read-only. Do not modify any application code.

### Quick discover pass

For a faster, non-exhaustive first pass:

> Start with a Discover phase only: run all 11 context tools, summarize what this app is (framework versions, database, route surface, model list, test coverage), and flag any immediate red flags in 3-5 bullets. Do not write findings yet.

### DSA / subsystem audit

For a bounded data-structure and ownership review:

> Use the laravel-audit-dsa skill. Inventory subsystems, review them in bounded read-only lanes, then rank P0–P3.

See [DSA audit](/dsa/).

## Read-only boundary

Auditing must not modify application code. Installation may write Auditor-owned resources (skills, guidelines, adapters, MCP config). It will not overwrite user-owned adapter files or an existing `laravel-auditor` MCP entry without `--force`.
