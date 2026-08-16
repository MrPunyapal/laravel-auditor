---
name: laravel-auditor-development
description: >
  Integrate and apply the Laravel Auditor package in Laravel applications:
  installation, setup, the MCP tools, and the audit workflow. Use when setting
  up Laravel Auditor or wiring it into a Laravel app.
metadata:
  agent: any
---

# Laravel Auditor Development

Use this skill when a Laravel application needs to install, configure, or apply the Laravel Auditor package.

## Primary goals

- Install Laravel Auditor as a development dependency.
- Run the appropriate setup command (Boost-based or standalone).
- Verify setup with the diagnostics command.
- Use the audit workflow skill to perform an audit.

## Workflow

### 1. Install the package

```bash
composer require --dev mrpunyapal/laravel-auditor
```

### 2. Set up resources

When Laravel Boost is installed, expose Auditor resources through Boost:

```bash
php artisan boost:install
```

(or `php artisan boost:update` after package updates).

When Boost is not installed, use the standalone installer:

```bash
php artisan auditor:install
```

The installer is idempotent and safe. It detects the Laravel application context, detects whether Boost is installed, prepares Auditor's agent-facing resources, and reports what it created or updated.

### 3. Verify setup

```bash
php artisan auditor:status
```

### 4. Inspect the available rules and project facts

```bash
php artisan auditor:rules
php artisan auditor:context --list
php artisan auditor:context project_info
```

### 5. Run an audit

Ask the agent to use the `laravel-audit` skill to perform a structured, evidence-based audit of the application.

### 6. Render a report

If the agent produced a JSON findings file:

```bash
php artisan auditor:report --example
php artisan auditor:report --findings=storage/auditor-findings.json --format=markdown
```

Supported formats: `markdown`, `json`, `text`. Finding schema: publish tag `laravel-auditor-schema`.

### 7. Optional MCP

```bash
php artisan auditor:mcp
```

Register that stdio command with the agent so it can call `project_info`, `routes`, `models`, `migrations`, `database_schema`, `dependencies`, `configuration`, `policies_authorization`, `jobs_events_schedules`, and `tests`.

With Laravel Boost installed, the same context tools are registered automatically inside Boost's MCP server (via `boost.mcp.tools.include`); no extra setup is needed.

## References

- Install: `composer require --dev mrpunyapal/laravel-auditor`
- Boost path: `php artisan boost:install` / `php artisan boost:update`
- Standalone path: `php artisan auditor:install` (`--dry-run`, `--force`)
- Diagnostics: `php artisan auditor:status`
- Rules: `php artisan auditor:rules` (`--domain=`, `--json`)
- Context: `php artisan auditor:context` (`--list`, `{collector}`, `--output=`)
- Reports: `php artisan auditor:report` (`--findings=`, `--example`, `--format=markdown|json|text|sarif`, `--output=`)
- CI: `php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high`
- Applicable rules: `php artisan auditor:rules --applicable`
- Facade: `LaravelAuditor::collect('routes')`, `LaravelAuditor::rules()`
- Config publish tag: `laravel-auditor-config`
- Resource publish tag: `laravel-auditor-resources`
- Schema publish tag: `laravel-auditor-schema`
- Example publish tag: `laravel-auditor-examples`
- Audit workflow skill: `laravel-audit`

## Examples

Ask the agent:

> Use the laravel-audit skill. Discover this Laravel app, scope the relevant domains, and report only findings with file, route, or schema evidence.

A finding should include `rule_id`, `severity`, `confidence`, `summary`, `why_it_matters`, `evidence`, and `recommendation`.

Full audit prompt:

> You are auditing the Laravel application in this project using the Laravel Auditor methodology.
>
> 1. Use the laravel-audit skill. Follow its Discover → Scope → Verify → Report workflow.
> 2. Start by calling the context MCP tools to gather deterministic facts BEFORE reading code: `project_info`, `routes`, `models`, `migrations`, `database_schema`, `dependencies`, `configuration`, `authorization`, `jobs_events_schedules`, `tests`.
> 3. Scope the relevant domains (security, database, architecture, testing, ...). Pick the domains with the most risk signal and go deep.
> 4. For every potential finding, verify against actual files, routes, or schema. Never report a guess.
> 5. Report findings ranked P0–P3, each with: file/route/schema evidence, the rule violated, why it matters, and a concrete fix.
> 6. Be read-only. Do not modify any application code.

Discover-only quick pass:

> Start with a Discover phase only: run all 11 context tools, summarize what this app is (framework versions, database, route surface, model list, test coverage), and flag any immediate red flags in 3-5 bullets. Do not write findings yet.

## Anti-patterns

- Do not require Boost just to use Auditor.
- Do not run `php artisan auditor:install` when Boost is installed unless a standalone path is explicitly wanted.
- Do not modify application code during setup or during an audit.
- Do not treat Auditor as an autonomous scanner that invents findings without evidence.