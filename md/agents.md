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

That is enough to start a full audit. Ready-to-use prompts for common scenarios — full audit, quick discover pass, domain-focused audits, filtered verification of a single suspicion, re-audits after fixes, and DSA reviews — live in one place:

See [Prompt examples](/prompts/).

## Read-only boundary

Auditing must not modify application code. Installation may write Auditor-owned resources (skills, guidelines, adapters, MCP config). It will not overwrite user-owned adapter files or an existing `laravel-auditor` MCP entry without `--force`.
