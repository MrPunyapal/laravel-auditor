<div align="center">
    <h1>Laravel Auditor</h1>
</div>

<p align="center">
    <a href="https://packagist.org/packages/mrpunyapal/laravel-auditor"><img src="https://img.shields.io/packagist/v/mrpunyapal/laravel-auditor.svg?style=flat-square" alt="Packagist"></a>
    <a href="https://packagist.org/packages/mrpunyapal/laravel-auditor"><img src="https://img.shields.io/packagist/php-v/mrpunyapal/laravel-auditor.svg?style=flat-square" alt="PHP from Packagist"></a>
    <a href="https://badge.laravel.cloud/badge/mrpunyapal/laravel-auditor?style=flat"><img src="https://badge.laravel.cloud/badge/mrpunyapal/laravel-auditor?style=flat" alt="Laravel versions"></a>
    <a href="https://github.com/mrpunyapal/laravel-auditor/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/mrpunyapal/laravel-auditor/tests.yml?branch=main&label=Tests&style=flat-square"></a>
    <a href="https://packagist.org/packages/mrpunyapal/laravel-auditor"><img src="https://img.shields.io/packagist/dt/mrpunyapal/laravel-auditor.svg?style=flat-square" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/mrpunyapal/laravel-auditor"><img src="https://badge.laravel.cloud/badge/mrpunyapal/laravel-auditor?style=flat-square" alt="Laravel Version Compatibility"></a>
    <a href="https://packagist.org/packages/mrpunyapal/laravel-auditor"><img src="https://badge.laravel.cloud/php-badge/mrpunyapal/laravel-auditor?style=flat-square" alt="PHP Version Compatibility"></a>
    <a href="https://laravel.com/docs/boost#third-party-package-skills"><img src="https://badge.laravel.cloud/boost-badge.svg?style=flat-square" alt="Laravel Boost Skill"></a>
</p>

Laravel Auditor equips an existing AI coding agent with a specialized, evidence-based methodology and toolset for auditing Laravel applications.

**Docs:** [mrpunyapal.github.io/laravel-auditor](https://mrpunyapal.github.io/laravel-auditor)

This is **early 0.1.x**. Install it as a development dependency, wire it into Claude / Codex / Cursor / Boost (or another agent), and ask that agent to audit. It is **not** a one-click scanner or an autonomous product. Finding quality depends on the agent following the skill.

Requirements: **PHP 8.3+**, **Laravel 12 or 13**. Command names, config keys, MCP tool names, finding fields, and rule IDs stay compatible across 0.1.x.

## Why it exists

AI agents can already read a Laravel codebase. They still tend to:

- guess at framework behavior instead of collecting project facts
- report style opinions as high-severity issues
- invent vulnerabilities from uncommon patterns
- skip verification before reporting a serious finding

Laravel Auditor gives the agent a repeatable workflow and deterministic project context so it is less likely to guess. Findings are only as good as the agent that follows the skill.

## Supported agent workflow

The package is agent-agnostic. Audit knowledge lives once and is adapted thinly for:

- Codex
- Claude Code
- Gemini CLI
- other agents that consume project instructions, skills, guidelines, or MCP tools

When Laravel Boost is installed, Auditor integrates through Boost's third-party guidelines and skills. When Boost is absent, `php artisan auditor:install --agents=...` publishes standalone agent resources.

The first five minutes should look like this:

1. Install the package as a development dependency
2. Run `boost:install` or `auditor:install --agents=claude_code` (or your agent)
3. Open that AI agent
4. Ask it to use the `laravel-audit` skill
5. The agent produces structured findings; you can render them with `auditor:report`

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
php artisan auditor:install --agents=claude_code
```

Pass the agent you actually use (`opencode`, `claude_code`, `cursor`, `copilot`, `gemini`, `codex`, `junie`, `zed`). Interactive runs ask. Non-interactive runs with no `--agents`, no config, and no project markers wire nothing.

The installer is idempotent and safe. It:

- detects the Laravel application context
- detects whether Laravel Boost is installed
- publishes agent skills and guidelines to `.ai/`
- asks which AI agent(s) the project uses (non-interactive runs resolve from `--agents`, then `laravel-auditor.agents` config, then project detection)
- writes thin `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, Cursor, Copilot, Codex, Junie, and Zed adapters only when those files are missing
- copies the `laravel-audit` skill into the selected agent's native skills directory
- registers the `laravel-auditor` MCP server in the selected agent's config (except Gemini)
- publishes finding/report schemas and an example findings file
- publishes `config/laravel-auditor.php` when it is missing
- reports what it created or left unchanged

Useful options:

```bash
php artisan auditor:install --dry-run
php artisan auditor:install --force
php artisan auditor:install --agents=opencode,claude_code
```

`--agents` restricts wiring to the listed agent keys (`opencode`, `claude_code`, `cursor`, `copilot`, `gemini`, `codex`, `junie`, `zed`). Non-interactive runs resolve agents from `--agents`, then `laravel-auditor.agents` config, then project detection. When none of those resolve, no agents are wired.

`--force` refreshes Auditor-owned resources. It does not overwrite unrelated user-owned files unless you explicitly ask it to refresh an existing adapter.

You can also publish resources with Artisan:

```bash
php artisan vendor:publish --tag="laravel-auditor"
php artisan vendor:publish --tag="laravel-auditor-config"
php artisan vendor:publish --tag="laravel-auditor-resources"
```

## What it audits

The current 0.1.x catalog focuses on six domains. The package does **not** execute these checks itself — the agent does:

| Domain | Looks for |
| --- | --- |
| Security | Authorization gaps, mass assignment, sensitive data, unsafe redirects, file handling, committed secrets, debug exposure |
| Performance | N+1 risks, expensive request-lifecycle work, missing indexes when query evidence exists |
| Architecture | Boundary violations, duplicated logic, unnecessary abstractions — without cargo-cult repository/service advice |
| Database | Schema/relationship mismatches, destructive migrations, nullability risks |
| Testing | Missing meaningful coverage, weak tests, missing authorization tests |
| Laravel conventions | Version-inappropriate APIs, reinvented framework features, lifecycle misuse |

The package also detects ecosystem signals (Livewire, Filament, Inertia, Pest, PHPUnit, Tailwind, queues) so later rule packs can attach cleanly. A rule ships only when it can meet the evidence-first standard.

List the current rules:

```bash
php artisan auditor:rules
php artisan auditor:rules --domain=security
php artisan auditor:rules --json
```

0.1.x ships **61** evidence-first rules, including optional Livewire, Filament, Inertia, Sanctum, and Pest packs that only apply when those packages are installed. Queue and DSA rules always apply. The full catalog is in [`resources/auditor/rules/RULES.md`](resources/auditor/rules/RULES.md).

```bash
php artisan auditor:rules --applicable
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

### Full example prompt

> You are auditing the Laravel application in this project using the Laravel Auditor methodology.
>
> 1. Use the laravel-audit skill. Follow its Discover → Scope → Verify → Report workflow.
> 2. Start by calling the context MCP tools to gather deterministic facts BEFORE reading code:
>    - `project_info` — PHP/Laravel versions, database, ecosystem signals
>    - `routes` — the full route surface
>    - `models` — all models with fillable/guarded, casts, relationships
>    - `migrations` — schema changes over time
>    - `database_schema` — actual tables/columns/indexes
>    - `dependencies` — installed packages and versions
>    - `configuration` — config keys in use
>    - `policies_authorization` — gates, policies, auth middleware
>    - `jobs_events_schedules` — queues, events, cron
>    - `tests` — test coverage layout
> 3. Scope the relevant domains (e.g., security, database, architecture, testing). Do NOT audit everything superficially — pick the domains with the most risk signal and go deep.
> 4. For every potential finding, verify against actual files, routes, or schema. Never report a guess.
> 5. Report findings ranked P0–P3, each with: file/route/schema evidence, the rule violated, why it matters, and a concrete fix.
> 6. Be read-only. Do not modify any application code.

For a quick Discover-only pass:

> Start with a Discover phase only: run all 11 context tools, summarize what this app is (framework versions, database, route surface, model list, test coverage), and flag any immediate red flags in 3-5 bullets. Do not write findings yet.

For a data-structure / ownership pass:

> Use the laravel-audit-dsa skill. Inventory subsystems, review them in bounded read-only lanes, then rank P0–P3.

More ready-to-use prompts — domain-focused audits, filtered verification of a single suspicion, and re-audits after fixes — live in the [prompt examples](https://laravel-auditor.com/prompts/) page.

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

## DSA / subsystem audit

For a read-only, orchestrated pass over **data structures, state, algorithms, and ownership**, use the `laravel-audit-dsa` skill. The coordinator inventories every subsystem, sends bounded read-only workers (at most two findings each), then validates, dedupes, and ranks **P0–P3**.

```bash
php artisan auditor:context subsystems
php artisan auditor:report --findings=storage/auditor-findings.json
```

Reports include a priority synthesis. Set `metadata.priority` to `p0`–`p3` when ranking explicitly.

## Collecting project facts

You do not need MCP to inspect the app. Dump any collector from Artisan:

```bash
php artisan auditor:context --list
php artisan auditor:context project_info
php artisan auditor:context routes --output=storage/auditor-routes.json
```

Over MCP, the `routes`, `models`, `database_schema`, and `dependencies` tools also accept optional read-only filters (for example `routes {uri: "api"}`) so an agent can verify a focused slice instead of pulling the whole inventory. See [MCP tools](https://laravel-auditor.com/mcp/).

Or from PHP:

```php
use LaravelAuditor\Facades\LaravelAuditor;

LaravelAuditor::collect('models');
```

## MCP tools

Register the local stdio server with your agent:

```bash
php artisan auditor:mcp
```

Example Claude Code registration:

```bash
claude mcp add -s local -t stdio laravel-auditor php artisan auditor:mcp -q
```

When Laravel Boost is installed, the same context collectors are also registered automatically as read-only tools inside Boost's `laravel-boost` MCP server (via `boost.mcp.tools.include`), so no extra setup is needed there.

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
| `jobs_events_schedules` | Jobs, events/listeners, schedules |
| `tests` | Framework, test case counts (feature/unit), file layout |
| `subsystems` | Ownership-bounded inventory for a DSA-style coordinator audit |

These tools are read-only. They return structured facts, not unfiltered source dumps.

## Reporting and diagnostics

```bash
php artisan auditor:status
php artisan auditor:report
php artisan auditor:report --example
php artisan auditor:report --format=json
php artisan auditor:report --format=text
php artisan auditor:report --findings=storage/auditor-findings.json --output=storage/auditor-report.md
php artisan auditor:report --findings=storage/auditor-findings.json --format=sarif
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
```

Finding and report JSON schemas live in `resources/auditor/schema`. See the [findings docs](https://mrpunyapal.github.io/laravel-auditor/findings/).

`auditor:report` does not invent findings. The agent produces findings; the command renders them as Markdown, JSON, or CLI text with project facts, domain scope, counts, key risks, evidence, and recommendations.

There is no web dashboard. Reports are CLI, Markdown, JSON, or SARIF.

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
    'agents' => [],
    'context' => [
        'composer_audit' => true,
        'test_listing' => false,
    ],
    'report' => [
        'format' => 'markdown',
    ],
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

## Compatibility

Laravel Auditor is meant to sit in an application as a development tool for a long time. Releases stay backward compatible: command names, config keys, MCP tool names, finding fields, and rule IDs are not removed or renamed. Breaking changes are rare and would require a major version with an explicit changelog note.

## Not in 0.1.x

Deferred work lives in the [future scope](https://mrpunyapal.github.io/laravel-auditor/future/) docs. That includes automatic fixes, historical baselines, a web dashboard, legacy/standalone runners, organization policy packs, and deeper ecosystem rule packs.

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
