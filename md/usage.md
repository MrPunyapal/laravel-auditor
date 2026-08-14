---
title: Usage
description: Run status, context, rules, reports, and CI with Laravel Auditor.
order: 3
slug: usage
---

The CLI is an installation, diagnostics, and reporting layer. The AI agent still does the reasoning.

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
