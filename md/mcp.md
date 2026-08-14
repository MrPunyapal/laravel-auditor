---
title: MCP tools
description: Register the Laravel Auditor stdio MCP server and the structured context tools it exposes.
order: 5
slug: mcp
---

Laravel Auditor ships a local stdio MCP server. Boost does **not** currently document a third-party MCP extension API, so this server stays standalone.

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
| `tests` | Framework, file counts, suite layout |
| `subsystems` | Ownership-bounded inventory for a DSA-style coordinator audit |

The same payloads are available without MCP via `php artisan auditor:context {tool}`.

Output is structured and concise. Tools never mutate the application.
