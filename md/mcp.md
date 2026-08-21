---
title: MCP tools
description: Register the Laravel Auditor stdio MCP server and the structured context tools it exposes.
og_title: Laravel Auditor MCP tools
og_description: Structured, read-only context tools your agent can call before auditing a Laravel application.
order: 6
slug: mcp
---

MCP does not audit the app. It only answers the agent's questions with structured Laravel facts (routes, models, schema, and so on) so the agent does not have to guess from raw files.

Laravel Auditor ships a local stdio MCP server that exposes 11 read-only context tools. When Laravel Boost is installed, the same tools are also registered inside Boost's MCP server automatically.

## Register the server

```bash
php artisan auditor:mcp
```

For example, with Claude Code:

```bash
claude mcp add -s local -t stdio laravel-auditor php artisan auditor:mcp -q
```

A client configuration example lives in `resources/auditor/mcp/mcp.json.example`.

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

## Optional filters

Four tools accept optional read-only filter arguments so an agent can verify a focused slice instead of pulling the whole inventory. Calling a tool without arguments returns the complete payload exactly as before.

| Tool | Filter | Matches |
| --- | --- | --- |
| `routes` | `uri`, `name`, `action` | Case-insensitive substring |
| `routes` | `method` | Exact HTTP verb (`GET`, `POST`, ...) |
| `models` | `class`, `table` | Case-insensitive substring |
| `database_schema` | `table` | Case-insensitive substring |
| `dependencies` | `package` | Case-insensitive substring on installed package names |

Example: list only routes under `/api`:

```json
{ "name": "routes", "arguments": { "uri": "api/" } }
```

When a filter is applied, the response keeps every documented field and adds two keys:

- `filtered` — always `true`, so the agent knows the payload is a subset
- `total_count` — the size of the unfiltered inventory, so nothing is silently hidden

Combining filters narrows with AND semantics. Unknown filters are rejected with an error instead of being ignored, so a typo can never masquerade as an unfiltered result. The `dependencies` filter only narrows the `packages` map; `requires`, `requires_dev`, and `composer audit` advisories always stay complete so security data is never filtered away.

Tool output is encoded as compact JSON (no indentation) to keep responses token-lean without dropping any fields.

## Why structured data instead of source dumps

An agent can read files directly, but that gives it raw text without context. Laravel Auditor's tools return structured, filtered, deterministic data. The agent gets the route table as a list of methods, URIs, and middleware — not a PHP file it has to parse. This makes the agent's reasoning faster and more reliable.

## Without MCP

The same context is available without MCP through Artisan:

```bash
php artisan auditor:context project_info
php artisan auditor:context routes --output=storage/auditor-routes.json
php artisan auditor:context --list
```

From PHP:

```php
use LaravelAuditor\Facades\LaravelAuditor;

LaravelAuditor::collect('models');
```

MCP is a convenience for agents that support it. It does not expose any functionality that the Artisan commands do not already provide.

## Read-only

All tools are read-only. They return structured facts about the application. They never mutate code, configuration, or database state.

## Laravel Boost integration

When Laravel Boost is installed, the service provider registers the same 11 context collectors as read-only tools inside Boost's `laravel-boost` MCP server through `boost.mcp.tools.include`. No extra setup is needed — the tools appear in Boost's `tools/list` and run through Boost's subprocess executor.
