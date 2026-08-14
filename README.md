<div align="center">
    <h1>Laravel Auditor</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/mrpunyapal/laravel-auditor"><img src="https://img.shields.io/packagist/v/mrpunyapal/laravel-auditor.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/mrpunyapal/laravel-auditor"><img src="https://img.shields.io/packagist/php-v/mrpunyapal/laravel-auditor.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://badge.laravel.cloud/badge/mrpunyapal/laravel-auditor?style=flat"><img src="https://badge.laravel.cloud/badge/mrpunyapal/laravel-auditor?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/mrpunyapal/laravel-auditor/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/mrpunyapal/laravel-auditor/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/mrpunyapal/laravel-auditor"><img src="https://img.shields.io/packagist/dt/mrpunyapal/laravel-auditor.svg?style=flat-square" alt="Total Downloads"></a>
</p>

Laravel Auditor equips an existing AI coding agent with a specialized, evidence-based methodology and toolset for auditing Laravel applications.

It is **not** a generic code-review prompt, a replacement for Laravel Boost, or an autonomous AI product. The agent remains the reasoning engine. This package provides the audit workflow, domain knowledge, rules, finding schema, and structured Laravel context tools.

## Why it exists

AI agents can already read a Laravel codebase. They still tend to:

- guess at framework behavior instead of collecting project facts
- report style opinions as high-severity issues
- invent vulnerabilities from uncommon patterns
- skip verification before reporting a serious finding

Laravel Auditor gives the agent a repeatable audit workflow and deterministic project context so findings stay specific, evidenced, and trustworthy.

## Supported agent workflow

The package is agent-agnostic. Audit knowledge lives once and is adapted thinly for:

- Codex
- Claude Code
- Gemini CLI
- other agents that consume project instructions, skills, guidelines, or MCP tools

When Laravel Boost is installed, Auditor integrates through Boost's third-party guidelines and skills. When Boost is absent, `php artisan auditor:install` publishes standalone agent resources.

The first five minutes should look like this:

1. Install the package as a development dependency
2. Run Boost setup or `auditor:install`
3. Open your preferred AI agent
4. Ask it to audit the project
5. Receive structured findings with evidence

## Installation

Install the package as a development dependency:

```bash
composer require --dev mrpunyapal/laravel-auditor
```

### With Laravel Boost

If the application already uses Laravel Boost, expose Auditor's guidelines and skills through Boost:

```bash
php artisan boost:install
```

After package updates:

```bash
php artisan boost:update
```

Do not run `auditor:install` just to duplicate Boost setup. Boost consumes `resources/boost/guidelines` and `resources/boost/skills` from this package directly.

### Standalone (no Boost)

```bash
php artisan auditor:install
```

The installer is idempotent and safe. It:

- detects the Laravel application context
- detects whether Laravel Boost is installed
- publishes agent skills and guidelines to `.ai/`
- writes thin `AGENTS.md` and `CLAUDE.md` adapters only when those files are missing
- publishes `config/laravel-auditor.php` when it is missing
- reports what it created or left unchanged

Useful options:

```bash
php artisan auditor:install --dry-run
php artisan auditor:install --force
```

`--force` refreshes Auditor-owned resources. It does not overwrite unrelated user-owned files unless you explicitly ask it to refresh an existing adapter.

You can also publish resources with Artisan:

```bash
php artisan vendor:publish --tag="laravel-auditor"
php artisan vendor:publish --tag="laravel-auditor-config"
php artisan vendor:publish --tag="laravel-auditor-resources"
```

## What V1 audits

V1 focuses on six domains:

| Domain | Looks for |
| --- | --- |
| Security | Authorization gaps, mass assignment, sensitive data, unsafe redirects, file handling, committed secrets, debug exposure |
| Performance | N+1 risks, expensive request-lifecycle work, missing indexes when query evidence exists |
| Architecture | Boundary violations, duplicated logic, unnecessary abstractions — without cargo-cult repository/service advice |
| Database | Schema/relationship mismatches, destructive migrations, nullability risks |
| Testing | Missing meaningful coverage, weak tests, missing authorization tests |
| Laravel conventions | Version-inappropriate APIs, reinvented framework features, lifecycle misuse |

The package also detects ecosystem signals (Livewire, Filament, Inertia, Pest, PHPUnit, Tailwind, queues) so later rule packs can attach cleanly. V1 only ships a rule when it can meet the evidence-first standard.

List the current rules:

```bash
php artisan auditor:rules
php artisan auditor:rules --domain=security
php artisan auditor:rules --json
```

## Example audit interaction

Ask the agent:

> Use the laravel-audit skill to audit this application. Discover the project first, scope the relevant domains, and report only evidenced findings.

The agent should:

1. Collect project facts (`auditor:status` or the MCP tools)
2. Scope the domains that actually apply
3. Investigate with routes, models, schema, policies, tests, and source
4. Verify high-severity claims before reporting them
5. Produce structured findings and a report

## Example finding

```json
{
  "id": "F-2026-0001",
  "rule_id": "AUD-SEC-001",
  "title": "Missing authorization boundary",
  "domain": "security",
  "severity": "high",
  "confidence": "confirmed",
  "status": "open",
  "summary": "Any authenticated user can delete another user's post.",
  "why_it_matters": "The destroy action never authorizes the Post policy.",
  "evidence": [
    {
      "type": "file",
      "reference": "app/Http/Controllers/PostController.php",
      "line": 42,
      "end_line": 48
    }
  ],
  "affected_resources": ["app/Http/Controllers/PostController.php"],
  "symbol": "App\\Http\\Controllers\\PostController@destroy",
  "recommendation": "Authorize the deletion with a PostPolicy or route middleware."
}
```

Severity: `critical`, `high`, `medium`, `low`, `info`.

Confidence: `confirmed`, `high`, `medium`, `low`.

## MCP tools

Register the local stdio server with your agent:

```bash
php artisan auditor:mcp
```

Example Claude Code registration:

```bash
claude mcp add -s local -t stdio laravel-auditor php artisan auditor:mcp
```

Tools:

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
| `jobs_events_schedules` | Jobs, events/listeners, scheduled commands |
| `tests` | Framework, file counts, suite layout |

These tools are read-only. They return structured facts, not unfiltered source dumps.

## Reporting and diagnostics

```bash
php artisan auditor:status
php artisan auditor:report
php artisan auditor:report --format=json
php artisan auditor:report --format=text
php artisan auditor:report --findings=storage/auditor-findings.json --output=storage/auditor-report.md
```

`auditor:report` does not invent findings. The agent produces findings; the command renders them as Markdown, JSON, or CLI text with project facts, domain scope, counts, key risks, evidence, and recommendations.

V1 does not include a web dashboard.

## Architecture

```text
src/
  Audit/          rules, findings, evidence, domains, report renderers
  Context/        read-only Laravel collectors used by MCP and reports
  Console/        install, status, rules, report, mcp
  MCP/            stdio MCP server
  Support/        Boost detection
resources/
  auditor/        agent-agnostic skills, guidelines, and rules
  boost/          third-party Boost guidelines and skills
```

Audit knowledge stays agent-neutral. `AGENTS.md` / `CLAUDE.md` adapters only point at that source of truth.

## Configuration

Publish the config file to change the default domain list, extra rule directories, or standalone resource target:

```php
return [
    'domains' => [
        'security',
        'performance',
        'architecture',
        'database',
        'testing',
        'conventions',
    ],
    'rules' => [
        // base_path('auditor/rules'),
    ],
    'resources_target' => '.ai',
];
```

## Trustworthiness

The agent is instructed to:

- say when evidence is incomplete
- distinguish confirmed findings from hypotheses
- avoid inventing package or runtime behavior
- avoid claiming exploitability without evidence
- avoid recommending upgrades only because a package is old
- keep style preferences out of high-severity findings

A short, evidenced report is the intended product.

## Not in V1

These are intentionally deferred:

- automatic code fixes or pull requests
- a web dashboard or hosted audit service
- CI-as-a-product, baselines, and historical trend reporting
- a giant rule library or CVE scanner
- standalone / legacy Laravel runners
- organization policy packs

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Thank you for considering contributing to Laravel Auditor! Please review our [contributing guide](.github/CONTRIBUTING.md) to get started.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Punyapal Shah](https://github.com/mrpunyapal)
- [All Contributors](../../contributors)

## License

Laravel Auditor is open-sourced software licensed under the [MIT license](LICENSE.md).
