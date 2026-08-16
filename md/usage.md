---
title: Usage
description: Run status, context, rules, reports, and CI with Laravel Auditor.
og_title: Using Laravel Auditor
og_description: Run status, context, rules, reports, and CI with the Laravel Auditor command line.
order: 3
slug: usage
---

The CLI is an installation, diagnostics, and reporting layer. The AI agent still does the reasoning.

## Auditing a project end-to-end

1. Install as a development dependency:

```bash
composer require --dev mrpunyapal/laravel-auditor
```

2. Expose the audit knowledge to your agent. With Laravel Boost: `php artisan boost:install` (re-run `php artisan boost:update` after package updates, or `boost:update --discover` to pick up newly installed packages). Without Boost: `php artisan auditor:install`.

3. (Optional) Register the read-only MCP context tools with your agent:

```bash
php artisan auditor:mcp
```

For example, with Claude Code:

```bash
claude mcp add -s local -t stdio laravel-auditor php artisan auditor:mcp
```

The agent can also gather the same facts without MCP via `auditor:context`.

4. Ask your agent to audit the project:

> Use the laravel-audit skill to audit this application. Discover the project first, scope the relevant domains, and report only evidenced findings.

The agent follows the skill workflow: **Discover** deterministic facts, **Scope** the domains that apply, **Investigate** with source and context, **Verify** high-severity claims, and **Report** structured findings with evidence.

5. Render or gate the findings the agent produced:

```bash
php artisan auditor:report --findings=storage/auditor-findings.json --format=markdown
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
```

## Inspect the project

```bash
php artisan auditor:status
php artisan auditor:context --list
php artisan auditor:context project_info
php artisan auditor:context subsystems
php artisan auditor:context routes --output=storage/auditor-routes.json
```

From PHP:

```php
use LaravelAuditor\Facades\LaravelAuditor;

LaravelAuditor::collect('models');
LaravelAuditor::rules()->count();
```

## List rules

```bash
php artisan auditor:rules
php artisan auditor:rules --domain=security
php artisan auditor:rules --applicable
php artisan auditor:rules --json
```

`--applicable` hides packs whose packages are not installed (for example Livewire rules on an app without Livewire).

## Render a report

The agent writes findings JSON. Auditor renders it.

```bash
php artisan auditor:report --example
php artisan auditor:report --findings=storage/auditor-findings.json
php artisan auditor:report --findings=storage/auditor-findings.json --format=json
php artisan auditor:report --findings=storage/auditor-findings.json --format=sarif
php artisan auditor:report --findings=storage/auditor-findings.json --output=storage/auditor-report.md
```

Formats: `markdown`, `json`, `text`, `sarif`.

Reports include project facts, severity and domain counts, a **P0–P3 priority synthesis**, evidence, and recommendations.

## CI

```bash
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high --format=sarif --output=auditor.sarif
```

CI fails when an **open** finding meets or exceeds `--fail-on` (`critical`, `high`, `medium`, `low`, `info`).

## Configuration

Publish `config/laravel-auditor.php` to change the default domain list, extra rule directories, standalone resource target, and default report format.
