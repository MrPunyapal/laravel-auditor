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

## Standalone adapters

`php artisan auditor:install` writes pointers only when the file is missing:

- `AGENTS.md`
- `CLAUDE.md`
- `GEMINI.md`
- `.cursor/rules/laravel-auditor.mdc`
- `.github/copilot-instructions.md`

They point at `.ai/skills/laravel-audit` and `.ai/guidelines/core.md`. They do not copy the full audit knowledge into every vendor file.

## Laravel Boost

When Boost is present, skip the standalone installer and run `boost:install` / `boost:update`.

Boost skills shipped by this package:

- `laravel-audit` — six-domain evidence-based audit
- `laravel-audit-dsa` — bounded subsystem / DSA audit
- `laravel-audit-security`, `performance`, `architecture`, `database`, `testing`, `conventions`
- `laravel-auditor-development` — install and wire the package

## Read-only

Auditing must not modify application code. Installation may write Auditor-owned resources; it will not overwrite user-owned files without `--force`.
