---
title: Installation
description: Install Laravel Auditor in a Laravel application with or without Laravel Boost.
og_title: Install Laravel Auditor
og_description: Add Laravel Auditor as a development dependency, then wire it into your AI agent or Laravel Boost.
order: 2
slug: installation
---

Laravel Auditor is installed as a **development** dependency. It is an engineering tool used during auditing, not a runtime requirement for the production application.

```bash
composer require --dev mrpunyapal/laravel-auditor
```

Requirements: PHP 8.3+ and Laravel 12 or 13.

## Decide your integration path

The installation path depends on whether your project uses Laravel Boost.

```text
Install Laravel Auditor
        │
        ├── Using Laravel Boost?
        │       └── Run boost:install / boost:update
        │
        └── Not using Boost?
                └── Run auditor:install
```

**If Laravel Boost is already installed**, do not run `auditor:install`. Boost consumes Auditor's skills and guidelines directly from the package's `resources/boost/` directory. Running `auditor:install` would duplicate what Boost already provides.

## With Laravel Boost

```bash
php artisan boost:install
```

After package updates:

```bash
php artisan boost:update
```

This exposes Auditor's audit-specific skills and guidelines through Boost. The context tools are also registered inside Boost's MCP server automatically. No additional setup is needed.

## Standalone (no Boost)

```bash
php artisan auditor:install
```

The standalone installer handles everything needed to connect Laravel Auditor to your AI agent.

### What it does

The installer is idempotent and safe. It:

- detects whether Laravel Boost is already installed
- publishes skills, guidelines, schemas, and examples to `.ai/`
- asks which AI agent(s) the project uses (or resolves them automatically)
- writes thin adapter files that point the agent at the shared audit knowledge
- copies the `laravel-audit` skill into the agent's native skills directory
- registers the `laravel-auditor` MCP server for agents that support MCP
- publishes `config/laravel-auditor.php` when it is missing
- reports exactly what it created or left unchanged

### What it will not overwrite

The installer respects your project. It will not:

- overwrite user-owned files (like a `CLAUDE.md` you wrote yourself) unless you pass `--force`
- duplicate Boost setup when Boost is detected
- modify application code

### Agent selection

When run interactively, the installer asks which AI agents to configure. In non-interactive environments (CI, scripts), agents are resolved in this order:

1. The `--agents` option (if provided)
2. Project detection (looks for agent config files like `CLAUDE.md`, `opencode.json`)
3. The `laravel-auditor.agents` config value
4. All supported agents as a fallback

### Options

```bash
# Preview what would be created without writing anything:
php artisan auditor:install --dry-run

# Refresh Auditor-owned resources (skills, guidelines, adapters):
php artisan auditor:install --force

# Wire only specific agents:
php artisan auditor:install --agents=claude_code,opencode
```

`--force` refreshes Auditor-owned resources. It appends to user-owned files rather than overwriting them, unless the file already contains an `<!-- laravel-auditor -->` marker block, in which case it replaces that block.

## Publish tags

You can also publish individual resource groups with Artisan:

```bash
php artisan vendor:publish --tag="laravel-auditor"
php artisan vendor:publish --tag="laravel-auditor-config"
php artisan vendor:publish --tag="laravel-auditor-resources"
php artisan vendor:publish --tag="laravel-auditor-schema"
php artisan vendor:publish --tag="laravel-auditor-examples"
```

## Verify

After installation, confirm everything is wired correctly:

```bash
php artisan auditor:status
php artisan auditor:rules --applicable
```

`auditor:status` shows the installed version, integration mode (Boost or standalone), audit domains, rule counts, and available context tools. `auditor:rules --applicable` lists only the rules that match your project's installed packages.

## Next

- [Agent setup](/agents/) — connect to your specific AI agent
- [Usage](/usage/) — audit workflow and commands
- [MCP tools](/mcp/) — register context tools with your agent
