---
title: Installation
description: Install Laravel Auditor in a Laravel application with or without Laravel Boost.
og_title: Install Laravel Auditor
og_description: Add Laravel Auditor as a development dependency, then wire it into your AI agent or Laravel Boost.
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
- asks which AI agent(s) the project uses (non-interactive runs resolve from `--agents`, project detection, or the `laravel-auditor.agents` config)
- writes thin `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, Cursor, Copilot, Codex, Junie, and Zed adapters only when those files are missing
- copies the `laravel-audit` skill into the selected agent's native skills directory
- registers the `laravel-auditor` MCP server in the selected agent's config (except Gemini, which has no MCP)
- publishes `config/laravel-auditor.php` when it is missing
- reports what it created or left unchanged

Useful options:

```bash
php artisan auditor:install --dry-run
php artisan auditor:install --force
php artisan auditor:install --agents=opencode,claude_code
```

`--agents` restricts wiring to the listed agent keys (`opencode`, `claude_code`, `cursor`, `copilot`, `gemini`, `codex`, `junie`, `zed`). In non-interactive runs, agents are resolved from `--agents`, then project detection, then the `laravel-auditor.agents` config, then all supported agents.

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
