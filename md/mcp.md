---
title: MCP tools
description: Register the Laravel Auditor stdio MCP server and the structured context tools it exposes.
og_title: Laravel Auditor MCP tools
og_description: Structured, read-only context tools your agent can call before auditing a Laravel application.
order: 5
slug: mcp
---

MCP (Model Context Protocol) is the bridge that lets an AI coding agent request structured Laravel application context from Auditor. Instead of reading raw files and guessing, the agent calls a tool and gets deterministic facts: routes, models, schema, dependencies, and more.

Laravel Auditor ships a local stdio MCP server that exposes 11 read-only context tools. When Laravel Boost is installed, the same tools are also registered inside Boost's MCP server automatically.

## Register the server

```bash
php artisan auditor:mcp
```

For example, with Claude Code:

```bash
claude mcp add -s local -t stdio laravel-auditor php artisan auditor:mcp
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
