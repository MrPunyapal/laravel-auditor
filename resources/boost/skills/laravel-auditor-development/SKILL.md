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

### 4. Inspect the available rules

```bash
php artisan auditor:rules
```

### 5. Run an audit

Ask the agent to use the `laravel-audit` skill to perform a structured, evidence-based audit of the application.

## Rules and references

- Audit workflow: the `laravel-audit` skill.
- Findings schema and rule metadata: `src/Audit/Findings` and `src/Audit/Rules`.
- Context tools: `src/Context`.

## Anti-patterns

- Do not require Boost just to use Auditor.
- Do not run `php artisan auditor:install` when Boost is installed unless a standalone path is explicitly wanted.
- Do not modify application code as part of setup.