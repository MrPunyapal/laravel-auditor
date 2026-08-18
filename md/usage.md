---
title: Usage
description: Run status, context, rules, reports, and CI with Laravel Auditor.
og_title: Using Laravel Auditor
og_description: Run status, context, rules, reports, and CI with the Laravel Auditor command line.
order: 3
slug: usage
---

The CLI provides diagnostics, context gathering, rule listing, and report rendering. The AI agent does the reasoning and produces the findings.

## Audit workflow

A complete audit follows these steps:

### 1. Install

```bash
composer require --dev mrpunyapal/laravel-auditor
```

### 2. Connect the agent

With Boost: `php artisan boost:install` (re-run `php artisan boost:update` after package updates, or `boost:update --discover` to pick up newly installed packages). Without Boost: `php artisan auditor:install`. See [Installation](/installation/).

### 3. Register context tools (optional)

If your agent supports MCP, register the read-only context tools so the agent can call them directly:

```bash
php artisan auditor:mcp
```

For example, with Claude Code:

```bash
claude mcp add -s local -t stdio laravel-auditor php artisan auditor:mcp -q
```

The agent can also gather the same facts without MCP via `auditor:context`. See [MCP tools](/mcp/).

### 4. Ask the agent to audit

Give the agent a clear instruction:

> Use the laravel-audit skill to audit this application. Discover the project first, scope the relevant domains, and report only evidenced findings.

The agent follows the skill workflow: **Discover** deterministic facts, **Scope** the domains that apply, **Investigate** with source and context, **Verify** high-severity claims, and **Report** structured findings with evidence.

### 5. Render the report

The agent writes findings as JSON. Auditor renders them:

```bash
php artisan auditor:report --findings=storage/auditor-findings.json --format=markdown
```

### 6. Gate CI (optional)

```bash
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
```

CI fails when an open finding meets or exceeds the severity threshold.

## Inspect the project

These commands help you understand what Auditor sees in your application.

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

`--applicable` hides ecosystem packs whose packages are not installed. For example, Livewire rules are hidden when Livewire is not a dependency.

## Render reports

```bash
php artisan auditor:report --example
php artisan auditor:report --findings=storage/auditor-findings.json
php artisan auditor:report --findings=storage/auditor-findings.json --format=json
php artisan auditor:report --findings=storage/auditor-findings.json --format=sarif
php artisan auditor:report --findings=storage/auditor-findings.json --output=storage/auditor-report.md
```

Formats: `markdown`, `json`, `text`, `sarif`.

Reports include project facts, severity and domain counts, a **P0-P3 priority synthesis**, evidence, and recommendations. See [Findings and reports](/findings/).

## CI

```bash
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high --format=sarif --output=auditor.sarif
```

CI output formats: `text`, `json`, `sarif`.

The `--fail-on` threshold accepts: `critical`, `high`, `medium`, `low`, `info`.

## Configuration

Publish `config/laravel-auditor.php` to change the default domain list, extra rule directories, standalone resource target, and default report format.

```bash
php artisan vendor:publish --tag="laravel-auditor-config"
```

Key settings:

- `domains` — which audit domains are advertised in reports
- `rules` — additional directories containing rule definition files
- `resources_target` — where the standalone installer publishes agent resources (default: `.ai`)
- `agents` — default agents for non-interactive installation
- `context.composer_audit` — enable the `composer audit` call from the dependencies collector (off by default)
- `context.test_listing` — enable accurate test case counting via `--list-tests` (off by default)
- `report.format` — default format for `auditor:report`
