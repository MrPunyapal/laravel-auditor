---
title: Usage
description: Run status, context, rules, reports, and CI with Laravel Auditor.
og_title: Using Laravel Auditor
og_description: Run status, context, rules, reports, and CI with the Laravel Auditor command line.
order: 3
slug: usage
---

You do not run a scan. You ask your AI agent to use the `laravel-audit` skill. The agent reasons and writes findings. These commands only inspect the app, list rules, or render what the agent produced.

## Audit workflow

A complete audit follows these steps:

### 1. Install

```bash
composer require --dev mrpunyapal/laravel-auditor
```

### 2. Connect the agent

With Boost: `php artisan boost:install` (re-run `php artisan boost:update` after package updates, or `boost:update --discover` to pick up newly installed packages). Without Boost: `php artisan auditor:install --agents=claude_code`. See [Installation](installation.md).

### 3. Register context tools (optional)

If your agent supports MCP, register the read-only context tools so the agent can call them directly:

```bash
php artisan auditor:mcp
```

For example, with Claude Code:

```bash
claude mcp add -s local -t stdio laravel-auditor php artisan auditor:mcp -q
```

The agent can also gather the same facts without MCP via `auditor:context`. See [MCP tools](mcp.md).

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
php artisan auditor:context changed_files
php artisan auditor:context review_scope
php artisan auditor:context routes --output=storage/auditor-routes.json
```

`changed_files` lists uncommitted paths — staged, unstaged, and untracked — so a review can be scoped to the work in progress instead of the whole application. It requires `git` on the host. When git or the repository is missing, the collector returns `available: false` with a `reason` rather than failing. That means the scope is unknown. A clean working tree returns zero files, which is a valid result.

`review_scope` is the default audit. `changed` is the same uncommitted set. `related` adds the view, test, or class those files directly use, and `scope` is the union. An agent reads `scope` and leaves the rest of the application alone. Ask for a whole-application audit when every file should be reviewed. A finding about a related file is still in the review, so render that findings file without `--dirty`. `--dirty` would drop it, because the related file may not be dirty itself.

Tune it with `changed_files.include_untracked`, `changed_files.ignore` (path prefixes), and `changed_files.max_files`.

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

Reports include project facts, severity and domain counts, a **P0-P3 priority synthesis**, evidence, and recommendations. See [Findings and reports](findings.md).

## CI

```bash
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high --format=sarif --output=auditor.sarif
```

CI output formats: `text`, `json`, `sarif`.

The `--fail-on` threshold accepts: `critical`, `high`, `medium`, `low`, `info`.

## Scope a run to a branch or to uncommitted work

Both `auditor:report` and `auditor:ci` accept `--base` and `--dirty`. `--base` is the one that gates a pull request: it keeps findings that reference a file in the committed diff between the merge base of that ref and `HEAD`. `--dirty` keeps findings that reference an uncommitted file. Unrelated pre-existing findings then do not dominate the output or fail the build.

```bash
php artisan auditor:ci --findings=storage/auditor-findings.json --base=origin/main --fail-on=high
php artisan auditor:report --findings=storage/auditor-findings.json --base=origin/main
php artisan auditor:report --findings=storage/auditor-findings.json --dirty
php artisan auditor:ci --findings=storage/auditor-findings.json --dirty --fail-on=high
```

`--base=origin/main` works on a clean CI checkout because the commits are still there. The ref must already exist locally. In GitHub Actions, set `fetch-depth: 0` on `actions/checkout` so `origin/main` is fetched. `--dirty` reads the working tree only, so on that same clean checkout it matches no files and gates nothing. Pass both flags when a local run should include committed branch work and uncommitted edits. `--base` alone does not include uncommitted files.

How the scope is decided:

- A finding is in scope when `evidence` or `affected_resources` names one of the changed files.
- Evidence types decide what a file is. `file`, `migration`, and `test` references count as paths; `route`, `config`, `symbol`, `query`, `dependency`, and `log` never do. An unrecognized type falls back to the file extension, so a new type keeps working.
- An absolute reference is resolved against the application base, so `/var/www/app/Models/User.php` matches `app/Models/User.php`.
- A finding with no file reference at all is kept, including when the diff is empty. It cannot be proven unrelated to the change, and dropping it would hide a real problem.
- A rename keeps both the old path and the new path, so a finding on either side stays in scope.
- The scope uses the same `changed_files.ignore` list and `changed_files.max_files` cap as the collector. `include_untracked` applies to `--dirty` only. Each run reports the base ref (when set), the file count, the scoped count, and the total.

Both flags need `git` on the host. If the scope cannot be resolved, the command fails with the reason. A missing `--base` ref fails the same way: the command does not fall back to reporting every finding. If the change set is larger than `changed_files.max_files`, the command fails rather than gating on a truncated list.

## Configuration

Publish `config/laravel-auditor.php` to change the default domain list, extra rule directories, standalone resource target, and default report format.

```bash
php artisan vendor:publish --tag="laravel-auditor-config"
```

Key settings:

- `domains` — which audit domains are advertised in reports
- `rules` — additional directories containing rule definition files
- `resources_target` — where the standalone installer publishes agent resources (default: `.ai`)
- `agents` — default agents for non-interactive installation (built-in keys or `custom_agents` keys)
- `custom_agents` — additional installer targets for agents that are not in the built-in list
- `context.composer_audit` — enable the `composer audit` call from the dependencies collector (on by default; it hits the network and waits up to 60 seconds per collection, so set `false` to skip the shell-out when context collection must stay fully offline or fast)
- `context.test_listing` — enable accurate test case counting via `--list-tests` (off by default)
- `changed_files.include_untracked` — include untracked paths in the `changed_files` collector and in `--dirty` (on by default). `--base` never includes untracked files
- `changed_files.ignore` — repository-relative path prefixes excluded from `changed_files`, `--dirty`, and `--base`
- `changed_files.max_files` — cap on the number of paths returned or gated (default `500`). A scoped command fails when the change set is larger
- `report.format` — default format for `auditor:report`
