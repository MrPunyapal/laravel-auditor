---
title: Installation
description: Install Laravel Auditor in a Laravel application with or without Laravel Boost.
order: 2
slug: installation
---

Install Laravel Auditor as a **development** dependency.

```bash
composer require --dev mrpunyapal/laravel-auditor
```

Requirements: PHP 8.3+ and Laravel 12 or 13.

## With Laravel Boost

If the application already uses [Laravel Boost](https://laravel.com/docs/boost), expose Auditor's guidelines and skills through Boost:

```bash
php artisan boost:install
```

After package updates:

```bash
php artisan boost:update
```

Do not run `auditor:install` just to duplicate Boost setup. Boost consumes `resources/boost/guidelines` and `resources/boost/skills` from this package directly.

## Standalone (no Boost)

```bash
php artisan auditor:install
```

The installer is idempotent and safe. It:

- detects the Laravel application context
- detects whether Laravel Boost is installed
- publishes agent skills, guidelines, schemas, and examples to `.ai/`
- writes thin `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, Cursor, and Copilot adapters only when those files are missing
- publishes `config/laravel-auditor.php` when it is missing
- reports what it created or left unchanged

Useful options:

```bash
php artisan auditor:install --dry-run
php artisan auditor:install --force
```

`--force` refreshes Auditor-owned resources. It does not overwrite unrelated user-owned files unless you explicitly ask it to refresh an existing adapter.

## Publish tags

```bash
php artisan vendor:publish --tag="laravel-auditor"
php artisan vendor:publish --tag="laravel-auditor-config"
php artisan vendor:publish --tag="laravel-auditor-resources"
php artisan vendor:publish --tag="laravel-auditor-schema"
php artisan vendor:publish --tag="laravel-auditor-examples"
```

## Verify

```bash
php artisan auditor:status
php artisan auditor:rules --applicable
```
