---
title: MCP tools
description: Register the Laravel Auditor stdio MCP server and the structured context tools it exposes.
og_title: Laravel Auditor MCP tools
og_description: Structured, read-only context tools your agent can call before auditing a Laravel application.
order: 5
slug: mcp
---

Laravel Auditor ships a local stdio MCP server, plus automatic registration of its context tools inside Laravel Boost's MCP server when Boost is installed.

The standalone server is available for agents that manage their own MCP config:

```bash
php artisan auditor:mcp
```

Claude Code:

```bash
claude mcp add -s local -t stdio laravel-auditor php artisan auditor:mcp
```

A client snippet lives in `resources/auditor/mcp/mcp.json.example`.

## Tools

| Tool | Returns |
| --- | --- |
| `project_info` | PHP/Laravel versions, database engine, ecosystem signals, source layout |
| `routes` | Methods, URIs, names, actions, middleware |
| `models` | Tables, fillable/guarded, casts, relationships |
| `migrations` | Migration files |
| `database_schema` | Tables, columns, indexes (read-only) |
| `dependencies` | Direct Composer requirements and versions |
| `configuration` | Config keys and a small set of non-secret values |
| `policies_authorization` | Gates, policies, auth middleware |
| `jobs_events_schedules` | Jobs, events/listeners, schedules |
| `tests` | Framework, test case counts (feature/unit), file layout |
| `subsystems` | Ownership-bounded inventory for a DSA-style coordinator audit |

The same payloads are available without MCP via `php artisan auditor:context {tool}`.

Output is structured and concise. Tools never mutate the application.

## Laravel Boost

When Laravel Boost is installed, the service provider registers the same context collectors as read-only tools (`project_info`, `routes`, `models`, `migrations`, `database_schema`, `dependencies`, `configuration`, `policies_authorization`, `jobs_events_schedules`, `tests`, `subsystems`) inside Boost's `laravel-boost` MCP server through `boost.mcp.tools.include`. No extra setup is needed — the tools appear in Boost's `tools/list` and run through Boost's subprocess executor.

