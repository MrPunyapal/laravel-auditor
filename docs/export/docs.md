# Laravel Auditor

> Evidence-based, agent-agnostic auditing tools and methodology for Laravel applications.

Laravel Auditor gives an existing AI coding agent the specialized knowledge, structured context, and repeatable methodology it needs to audit a Laravel application well.

It is an **early 0.1.x** development tool. It is **not** an autonomous scanner, a generic code-review prompt, or a replacement for Laravel Boost. You install it, open Claude / Codex / Cursor / Boost (or another agent), and ask it to audit. The agent remains the reasoning engine. Finding quality depends on that agent following the skill. Laravel Auditor provides the workflow, rules, finding schema, and read-only Laravel context tools.

Requirements: **PHP 8.3+** and **Laravel 12 or 13**. Public command names, config keys, MCP tool names, finding fields, and rule IDs are kept compatible across 0.1.x.

## Why it exists

AI agents can already read a Laravel codebase. Ask one to "audit my app" without specialized methodology and you will likely get:

- **Guesses about framework behavior** instead of collected project facts. The agent assumes how a package works rather than checking the installed version and its actual configuration.
- **Style opinions reported as high-severity issues**. The agent flags a naming convention it dislikes as a security risk.
- **Invented vulnerabilities from uncommon patterns**. The agent reports a theoretical attack vector that does not apply to this application's actual setup.
- **Serious findings reported without verification**. The agent claims a missing authorization boundary without checking the routes, middleware, or policies.

Laravel Auditor gives the agent a repeatable audit workflow and deterministic project context so it is less likely to guess. Findings are only as good as the agent that follows the skill.

## How it works

The audit follows a structured flow. The agent drives each stage; Laravel Auditor provides the tools and knowledge at each step.

```text
Discover → Scope → Investigate → Verify → Report
```

**Discover.** The agent gathers deterministic project facts: PHP and Laravel versions, database engine, route surface, models, migrations, dependencies, authorization setup, tests, and ecosystem packages. These facts come from Laravel Auditor's context tools, not from guessing.

**Scope.** The agent selects which audit domains actually apply. A queue-less app does not need queue analysis. A pure API app does not need Blade template review.

**Investigate.** The agent examines source code, traces behavior across files, and cross-checks evidence against multiple sources. Rules describe what to look for and what evidence is required.

**Verify.** Before reporting a high-severity finding, the agent verifies it against actual routes, middleware, models, schema, config, or tests. If verification fails, the finding is labeled with lower confidence.

**Report.** The agent produces structured findings with evidence. Laravel Auditor renders them as Markdown, JSON, text, or SARIF.

## What the package provides

Laravel Auditor contributes seven things to the audit:

| Component | Purpose |
| --- | --- |
| **Skills** | Step-by-step audit workflows the agent follows |
| **Guidelines** | Principles that govern every finding (evidence-first, read-only, no severity inflation) |
| **Rules** | 61 audit criteria across 6 core domains, describing what to look for and what evidence is required |
| **Context collectors** | 11 read-only tools that provide deterministic Laravel facts (routes, models, schema, etc.) |
| **MCP server** | A bridge that lets a supported AI agent call the context tools directly |
| **Finding schema** | A structured format for findings with severity, confidence, evidence, and recommendations |
| **Report and CI tooling** | Commands that render findings as Markdown, JSON, text, or SARIF, and gate CI on severity |

## What the AI agent provides

The agent provides the reasoning. It reads source code, traces behavior, evaluates context, applies judgment, and produces findings. Laravel Auditor does not replace the agent's intelligence — it focuses it.

## What it does not do

- **Not an autonomous service.** No AI runs without your trigger. You ask your agent to audit; the agent does the work.
- **Not a generic code reviewer.** Laravel Auditor is specifically designed for evidence-based Laravel auditing, not general code quality opinions.
- **Not a replacement for Laravel Boost.** When Boost is installed, Auditor extends it. When Boost is absent, Auditor works standalone.
- **Not a production monitor.** Auditor inspects code and configuration at a point in time. It does not observe runtime behavior.
- **Not an automatic fixer.** V1 is read-only. The agent recommends fixes; it does not apply them.

## Who is it for

Laravel developers who already use an AI coding agent (Claude Code, Codex, Cursor, Gemini, Boost, and similar) and want a more systematic, evidence-driven audit. If you have ever asked an agent to "review my Laravel app" and received a noisy list of opinions, this is for you.

It is not for teams that want a one-click scanner, a production monitor, or an automatic fixer.

## Quick start

```bash
composer require --dev mrpunyapal/laravel-auditor
```

Then either:

```bash
# With Laravel Boost installed:
php artisan boost:install

# Without Boost:
php artisan auditor:install --agents=claude_code
```

Open your AI agent and ask:

> Use the laravel-audit skill to audit this application. Discover the project first, scope the relevant domains, and report only evidenced findings.

The agent follows the skill workflow, uses the context tools to gather facts, applies the rules, and produces structured findings with evidence.

## Next

- [Installation](/installation/) — install and wire the package
- [Usage](/usage/) — commands, workflow, and reporting
- [Agent setup](/agents/) — connect to your specific AI agent


---

# Installation

> Install Laravel Auditor in a Laravel application with or without Laravel Boost.

Laravel Auditor is an **early 0.1.x** development dependency. It is an engineering tool used during auditing, not a runtime requirement and not a scanner that runs on its own.

```bash
composer require --dev mrpunyapal/laravel-auditor
```

Requirements: PHP 8.3+ and Laravel 12 or 13. After install, open your AI agent and ask it to use the `laravel-audit` skill. The agent does the reasoning; this package supplies the workflow and context.

## Decide your integration path

The installation path depends on whether your project uses Laravel Boost.

```text
Install Laravel Auditor
        │
        ├── Using Laravel Boost?
        │       └── Run boost:install / boost:update
        │
        └── Not using Boost?
                └── Run auditor:install --agents=...
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
php artisan auditor:install --agents=claude_code
```

The standalone installer publishes skills and wires the selected agent. Interactive runs ask which agents to configure. Non-interactive runs with no `--agents`, no config, and no project markers wire nothing.

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

When run interactively, the installer asks which AI agents to configure (pre-selecting any that were detected). In non-interactive environments (CI, scripts), agents are resolved in this order:

1. The `--agents` option (if provided)
2. The `laravel-auditor.agents` config value
3. Project detection (looks for agent-specific files like `CLAUDE.md`, `opencode.json`, or `.github/copilot-instructions.md`)

When none of those resolve, no agents are wired. Re-run with `--agents` to attach skills and MCP for a specific tool. A `.github` or `.vscode` directory alone is not treated as Copilot.

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

`auditor:status` shows the package version, integration mode (Boost or standalone), audit domains, rule counts, and available context tools. `auditor:rules --applicable` lists only the rules that match your project's installed packages.

## Next

- [Agent setup](/agents/) — connect to your specific AI agent
- [Usage](/usage/) — audit workflow and commands
- [MCP tools](/mcp/) — register context tools with your agent


---

# Usage

> Run status, context, rules, reports, and CI with Laravel Auditor.

You do not run a scan. You ask your AI agent to use the `laravel-audit` skill. The agent reasons and writes findings. These commands only inspect the app, list rules, or render what the agent produced.

## Audit workflow

A complete audit follows these steps:

### 1. Install

```bash
composer require --dev mrpunyapal/laravel-auditor
```

### 2. Connect the agent

With Boost: `php artisan boost:install` (re-run `php artisan boost:update` after package updates, or `boost:update --discover` to pick up newly installed packages). Without Boost: `php artisan auditor:install --agents=claude_code`. See [Installation](/installation/).

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


---

# Agent setup

> Wire Laravel Auditor into Codex, Claude Code, Gemini, Cursor, Copilot, and Laravel Boost.

Laravel Auditor does not run an audit for you. After install, you open your AI agent and ask it to use the `laravel-audit` skill.

Audit knowledge lives once under `resources/auditor`. Agent-specific files are thin adapters that point the agent at that shared knowledge. The same skills, guidelines, and rules work across every supported agent.

## Integration modes

### Laravel Boost

When Boost is installed, Auditor extends it. Boost consumes Auditor's skills and guidelines directly from `resources/boost/`. The context tools are also registered inside Boost's MCP server automatically through `boost.mcp.tools.include`.

If your project already uses Boost, this is the simplest path. Run `boost:install` or `boost:update` and everything is wired.

### Standalone

When Boost is absent, `auditor:install --agents=claude_code` (or your agent) handles the wiring. It publishes skills, guidelines, and schemas to `.ai/`, writes agent adapter files, and registers the MCP server for agents that support it.

Both paths produce the same audit knowledge — they just deliver it differently.

## Supported agents

The standalone installer supports eight agents:

| Agent | Guidelines | Skills | MCP |
| --- | --- | --- | --- |
| OpenCode | `AGENTS.md` | `.agents/skills` | `opencode.json` |
| Claude Code | `CLAUDE.md` | `.claude/skills` | `.mcp.json` |
| Cursor | `.cursor/rules/laravel-auditor.mdc` | `.cursor/skills` | `.cursor/mcp.json` |
| GitHub Copilot | `.github/copilot-instructions.md` | `.github/skills` | `.vscode/mcp.json` |
| Gemini CLI | `GEMINI.md` | `.gemini/skills` | — |
| Codex | `AGENTS.md` | `.agents/skills` | `.codex/config.toml` |
| Junie | `AGENTS.md` | `.junie/skills` | `.junie/mcp/mcp.json` |
| Zed | `AGENTS.md` | `.agents/skills` | `.zed/settings.json` |

Gemini does not support MCP. All other agents receive MCP registration when the installer runs.

## What an adapter file contains

An adapter is a short file that tells the agent where to find the audit skill and guidelines. For example, the `CLAUDE.md` adapter contains:

```markdown
# Laravel Auditor

This project uses Laravel Auditor for evidence-based Laravel audits.

When asked to audit, review, or assess this application, use the `laravel-audit` skill in `.ai/skills/laravel-audit` and follow `.ai/guidelines/core.md`.

Do not modify application code during an audit. Prefer deterministic project facts from `php artisan auditor:status`, `php artisan auditor:rules`, and the Laravel Auditor MCP tools.
```

The adapter does not contain the full skill, guidelines, or rules. It points at the shared copies in `.ai/`.

## Asking the agent to audit

Once the agent is wired, give it a clear instruction:

> Use the laravel-audit skill to audit this application. Discover the project first, scope the relevant domains, and report only evidenced findings.

### Full example prompt

> You are auditing the Laravel application in this project using the Laravel Auditor methodology.
>
> 1. Use the laravel-audit skill. Follow its Discover -> Scope -> Verify -> Report workflow.
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
>    - `tests` — test suite: framework, case counts (feature/unit)
>    - `subsystems` — ownership-bounded inventory for a DSA-style coordinator audit
> 3. Scope the relevant domains (e.g., security, database, architecture, testing). Do NOT audit everything superficially — pick the domains with the most risk signal and go deep.
> 4. For every potential finding, verify against actual files, routes, or schema. Never report a guess.
> 5. Report findings ranked P0–P3, each with: file/route/schema evidence, the rule violated, why it matters, and a concrete fix.
> 6. Be read-only. Do not modify any application code.

### Quick discover pass

For a faster, non-exhaustive first pass:

> Start with a Discover phase only: run all 11 context tools, summarize what this app is (framework versions, database, route surface, model list, test coverage), and flag any immediate red flags in 3-5 bullets. Do not write findings yet.

### DSA / subsystem audit

For a bounded data-structure and ownership review:

> Use the laravel-audit-dsa skill. Inventory subsystems, review them in bounded read-only lanes, then rank P0–P3.

See [DSA audit](/dsa/).

## Read-only boundary

Auditing must not modify application code. Installation may write Auditor-owned resources (skills, guidelines, adapters, MCP config). It will not overwrite user-owned adapter files or an existing `laravel-auditor` MCP entry without `--force`.


---

# MCP tools

> Register the Laravel Auditor stdio MCP server and the structured context tools it exposes.

MCP does not audit the app. It only answers the agent's questions with structured Laravel facts (routes, models, schema, and so on) so the agent does not have to guess from raw files.

Laravel Auditor ships a local stdio MCP server that exposes 11 read-only context tools. When Laravel Boost is installed, the same tools are also registered inside Boost's MCP server automatically.

## Register the server

```bash
php artisan auditor:mcp
```

For example, with Claude Code:

```bash
claude mcp add -s local -t stdio laravel-auditor php artisan auditor:mcp -q
```

A client configuration example lives in `resources/auditor/mcp/mcp.json.example`.

## Tools

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

## Why structured data instead of source dumps

An agent can read files directly, but that gives it raw text without context. Laravel Auditor's tools return structured, filtered, deterministic data. The agent gets the route table as a list of methods, URIs, and middleware — not a PHP file it has to parse. This makes the agent's reasoning faster and more reliable.

## Without MCP

The same context is available without MCP through Artisan:

```bash
php artisan auditor:context project_info
php artisan auditor:context routes --output=storage/auditor-routes.json
php artisan auditor:context --list
```

From PHP:

```php
use LaravelAuditor\Facades\LaravelAuditor;

LaravelAuditor::collect('models');
```

MCP is a convenience for agents that support it. It does not expose any functionality that the Artisan commands do not already provide.

## Read-only

All tools are read-only. They return structured facts about the application. They never mutate code, configuration, or database state.

## Laravel Boost integration

When Laravel Boost is installed, the service provider registers the same 11 context collectors as read-only tools inside Boost's `laravel-boost` MCP server through `boost.mcp.tools.include`. No extra setup is needed — the tools appear in Boost's `tools/list` and run through Boost's subprocess executor.


---

# Rules

> The evidence-first audit catalog and how applicability works.

Rules are metadata for the reasoning agent. They are not executable scanners.

A rule tells the agent what to investigate, what evidence is required, and what the typical severity and confidence are when a confirmed instance is found. The agent reads the rule, examines the application, and decides whether the rule actually applies.

```text
Rule
  ↓
Tells the agent what to investigate
  ↓
Specifies what evidence is required
  ↓
Agent investigates the application
  ↓
Agent decides whether the rule applies
  ↓
Agent produces a finding (or does not)
```

This is fundamentally different from a static analysis tool that flags code patterns automatically. Rules guide the agent's reasoning. The agent decides.

## List rules

```bash
php artisan auditor:rules
php artisan auditor:rules --applicable
php artisan auditor:rules --domain=security
php artisan auditor:rules --json
```

`--applicable` hides ecosystem packs whose packages are not installed. For example, Livewire rules are hidden when Livewire is not a dependency.

The authoritative definitions live in `resources/auditor/rules/*.php`. The human-readable catalog is `resources/auditor/rules/RULES.md`.

## Core domains

0.1.x ships 61 rules across six core domains:

| Domain | What it looks for |
| --- | --- |
| Security | Authorization, mass assignment, secrets, redirects, file handling, CSRF, XSS, SQL injection, debug exposure |
| Performance | N+1, request-lifecycle work, indexes, queues, cache only when justified |
| Architecture | Boundaries, duplication, unnecessary abstraction — no cargo-cult repositories |
| Database | Relationship/schema mismatch, destructive migrations, missing FKs |
| Testing | Missing meaningful coverage, weak tests, missing authorization tests |
| Conventions | Version-inappropriate APIs, reinvented framework features |

## Ecosystem packs

Most ecosystem packs apply only when the matching package is installed. Those packs are included in `--applicable` only when the required package is detected. Queue rules have no package constraint and always apply:

| Pack | Package required | Rule prefix |
| --- | --- | --- |
| Livewire | `livewire/livewire` | `AUD-LW-*` |
| Filament | `filament/filament` | `AUD-FIL-*` |
| Inertia | `inertiajs/inertia-laravel` | `AUD-IN-*` |
| Sanctum | `laravel/sanctum` | `AUD-API-*` |
| Pest | `pestphp/pest` | `AUD-PEST-*` |
| Queues | — | `AUD-QUE-*` |

DSA organizing-model rules (`AUD-DSA-*`) support the [DSA audit](/dsa/) skill.

## Severity and confidence

Each rule defines a typical severity and confidence:

- **Severity**: `critical`, `high`, `medium`, `low`, `info` — the impact of a confirmed instance.
- **Confidence**: how certain a properly-evidenced finding is for this rule.

A rule with high severity and high confidence means confirmed instances are typically serious. A rule with high severity and medium confidence means the pattern can be serious but evidence requirements are stricter.

## Evidence-first principle

Every rule specifies what evidence is required to support a finding. The agent must produce that evidence. A finding without evidence does not enter the report.

This is the core design constraint. Few high-quality rules beat a noisy catalog. Every shipped rule must meet the evidence-first standard. The package does not execute rules; the agent does.

## Writing custom rules

Each rule needs a stable ID, domain, severity, confidence, description, why it matters, recommendation, evidence requirements, and false-positive considerations. Rules are PHP files in a directory — add your custom directory to the `rules` config key.

Do not add a rule unless it can stay evidence-first.


---

# Findings and reports

> Finding schema, example payloads, and how reports are rendered.

Laravel Auditor does not invent findings. The agent produces them; the package stores, validates, and renders them.

This separation is deliberate. The agent reasons about the code and produces structured findings. Laravel Auditor validates those findings against its schema and renders them as reports. The package never decides what is a vulnerability — the agent does.

## Finding lifecycle

```text
AI agent
   ↓
reads code, applies rules, gathers evidence
   ↓
creates finding (JSON)
   ↓
Auditor loads and validates the finding
   ↓
Auditor renders it as Markdown, JSON, text, or SARIF
```

## Finding schema

See `resources/auditor/schema/finding.schema.json` and `resources/auditor/examples/findings.json`.

Required fields:

| Field | Purpose |
| --- | --- |
| `id` | Unique finding instance ID (e.g. `F-2026-0001`) |
| `rule_id` | Stable rule ID when one matches (e.g. `AUD-SEC-001`) |
| `title` | Short, specific description |
| `domain` | Audit domain (`security`, `performance`, `architecture`, `database`, `testing`, `conventions`) |
| `severity` | Impact level |
| `confidence` | How certain the finding is given available evidence |
| `summary` | What is wrong |
| `why_it_matters` | Why it matters for this application |

Include whenever possible:

| Field | Purpose |
| --- | --- |
| `evidence` | Concrete references (file paths, lines, routes, symbols, config keys) |
| `affected_resources` | Files, routes, config keys involved |
| `symbol` | Class, method, or route reference |
| `recommendation` | What to do about it |
| `remediation` | Optional step-by-step fix guidance |
| `verification_notes` | How the finding was verified, or why it could not be |

Optional `metadata`:

- `subsystem` — inventory ID from `auditor:context subsystems`
- `priority` — `p0`, `p1`, `p2`, or `p3`

## Severity and confidence

These are separate dimensions.

**Severity** describes impact: how much damage a confirmed instance could cause.

**Confidence** describes certainty: how well the evidence supports the claim.

A potentially critical issue with thin evidence should carry low confidence. A confirmed issue with verified evidence carries `confirmed` confidence. This distinction prevents the agent from inflating findings without proof.

- **Severity**: `critical`, `high`, `medium`, `low`, `info`
- **Confidence**: `confirmed`, `high`, `medium`, `low`

## Priority tiers

Reports assign each finding a priority tier (P0-P3). If `metadata.priority` is not set, Auditor derives it:

| Tier | Meaning | Auto-assigned when |
| --- | --- | --- |
| P0 | Correctness, security, or data-loss risk | Critical severity, or high severity in security/database |
| P1 | Concrete correctness or high-leverage work | High severity, or medium severity in security |
| P2 | Material invariant improvements | Medium severity |
| P3 | Telemetry, diagnostics, maintainability | Low or info severity |

Every promoted ID appears exactly once in the priority synthesis.

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

## Reports

The agent writes findings as JSON. Auditor renders them:

```bash
php artisan auditor:report --findings=storage/auditor-findings.json --format=markdown
php artisan auditor:report --findings=storage/auditor-findings.json --format=json
php artisan auditor:report --findings=storage/auditor-findings.json --format=text
php artisan auditor:report --findings=storage/auditor-findings.json --format=sarif
php artisan auditor:report --example
```

Reports include project facts, severity and domain counts, a priority synthesis, key risks, and all findings with evidence and recommendations.

## CI gating

```bash
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high --format=sarif --output=auditor.sarif
```

CI fails when an open finding meets or exceeds the `--fail-on` threshold.


---

# DSA audit

> Bounded, read-only subsystem audit for data structures, state, algorithms, and ownership.

A DSA (Data Structures, State, Algorithms) audit is still run by your AI agent, using the `laravel-audit-dsa` skill. It is a different kind of review from a security pass: it examines how data is structured, how state is represented, where behavior is owned, and whether the code could be materially simplified. The package only inventories subsystems and renders the agent's findings.

This is **audit only**. Do not edit files, implement fixes, commit, or push.

## What DSA looks for

- Scattered booleans or nullable fields that permit invalid combinations and should be a state machine
- Repeated assumptions about object shape that need a shared typed model
- Duplicated branching that a small registry would remove
- Unclear ownership of state or behavior
- Repeated scans, transformations, or lookups where a more appropriate collection or index would simplify
- Lifecycle or async states whose representation permits stale or contradictory state

The goal is not cosmetic consistency. It is material simplification — finding places where a small, boring structural change would remove complexity.

## What a subsystem is

A subsystem is an ownership boundary within the application. The `subsystems` context tool inventories them automatically: HTTP controllers, Eloquent models, authorization, database/migrations, tests, configuration, and dependencies.

Each subsystem gets a stable ID, a descriptive name, an exact boundary (which files belong to it), and relevant collectors. The audit reviews one subsystem at a time.

## How it works

The `laravel-audit-dsa` skill uses a coordinator pattern:

### 1. Establish the coverage contract

Inventory every subsystem. Start from `php artisan auditor:context subsystems`, then add any leftover `app/` areas. Each subsystem gets a status: `queued`, `in review`, `recommend`, or `skip`.

### 2. Run bounded reviews

One worker per subsystem. Each worker looks for at most two materially useful simplifications within its boundary. Workers are bounded — they do not expand into other subsystems.

Boundedness matters because unbounded agents tend to recommend cross-cutting refactors that relocate complexity rather than removing it. Bounded workers stay focused.

### 3. Validate and synthesize

The coordinator independently verifies every finding against the repository. Vague, duplicate, or complexity-relocating recommendations are rejected. Accepted findings are deduplicated and assigned to one authoritative subsystem.

### 4. Audit the audit

Before finishing, the coordinator checks for coverage gaps, duplication, over-abstraction, and schema completeness. Then ranks findings by impact, confidence, effort, blast radius, and prerequisites.

## Ranking

Findings are ranked into four tiers:

| Tier | Meaning |
| --- | --- |
| P0 | Reachable wrong-record, lost-update, authorization, durable-state, or incomplete-operation risk |
| P1 | Concrete boundary / high-leverage ownership fixes |
| P2 | Useful invariants, narrower impact or sensitive migration |
| P3 | Telemetry / diagnostics / maintainability — keep this small |

Every promoted ID appears exactly once.

## Running a DSA audit

```bash
php artisan auditor:context subsystems
php artisan auditor:report --findings=storage/auditor-findings.json
```

Ask your agent:

> Use the laravel-audit-dsa skill. Inventory subsystems, review them in bounded read-only lanes, then rank P0–P3.

The full workflow and worker brief are defined in the `laravel-audit-dsa` skill at `.ai/skills/laravel-audit-dsa/SKILL.md`.


---

# Future scope

> Features that may influence architecture but are not part of V1.

These features may influence later versions. They are **not** in 0.1.x and are not promises.

## Legacy and standalone support

- **F1: Legacy runner** — Audit older Laravel applications without forcing PHP or Laravel upgrades.
- **F2: Standalone CLI** — A package-runner style command for projects whose runtime is too old for the current package.

## Agent and runtime improvements

- **F8: Runtime verification** — Safe, opt-in checks that turn likely findings into confirmed ones by observing actual behavior.

## Remediation

- **F3: Auto-fix / remediation** — Turn a verified finding into a proposed fix, tests, and a patch. Any implementation must require explicit user approval and stay read-only by default.

## CI and baselines

- **F4: CI policies** — Ignored findings, historical baselines, new-vs-existing detection, trend tracking. `auditor:ci` already fails on severity.

## Audit depth

- **F5: More domains** — Deployment, observability, API design domains — only when they stay evidence-first.
- **F6: Deeper ecosystem packs** — Tailwind, Passport, PHPUnit-specific packs — only when they stay evidence-first.

## Reporting

- **F7: Richer reporting** — HTML output, GitHub annotations, PR comments, TUI, IDE integration. SARIF is already available.

## Broader scope

- **F9: Organization policy packs** — Multi-tenant or organization-level rule configuration.
- **F10: Agent profiler** — Understand which agents produce the best findings.
- **F11: Other-framework auditors** — Separate products for non-Laravel frameworks.

## Constraints that survive

These principles apply regardless of which features ship:

- Agent-agnostic core
- Boost optional
- Evidence over guesses
- Read-only by default
- Few high-quality rules
- Never claim more certainty than the evidence supports


---

# Contributing

> How to work on Laravel Auditor locally, including documentation.

Thank you for considering contributing to Laravel Auditor. Please also read [CONTRIBUTING.md](https://github.com/mrpunyapal/laravel-auditor/blob/main/.github/CONTRIBUTING.md).

## Local setup

```bash
composer install
composer build
```

The `build` command sets up the workbench with a SQLite database and runs migrations.

## Run the full validation suite

```bash
composer test
```

This runs PHPStan, Pint, type coverage, and Pest in sequence.

## Individual checks

```bash
composer lint          # Fix code style with Pint
composer lint:check    # Check code style without modifying
composer analyse       # Static analysis with PHPStan
composer test:unit     # Run Pest tests in parallel
composer test:types    # Type coverage (must be 100%)
```

## Documentation

Markdown source lives in `md/`. The static site is generated into `docs/` with [Docsmith](https://github.com/MrPunyapal/docsmith).

```bash
composer docs:build
```

**Do not edit `docs/` directly.** Edit the Markdown source in `md/` and rebuild.

### Adding a page

1. Create `md/your-page.md` with frontmatter:
   ```yaml
   ---
   title: Your Page
   description: What this page covers.
   og_title: Social title for the page
   og_description: Social description for the page
   order: 11
   slug: your-page
   ---
   ```
2. Run `composer docs:build`.
3. The page appears in the navigation based on its `order` value.

### Open Graph images

OG images are generated per page. They need Node.js, Playwright, and capturist installed once:

```bash
npm install
npx playwright install chromium
```

Set `DOCS_CAPTURE_OG=0` to skip capture (for example, in environments without Node.js).

Page-specific social titles and descriptions use `og_title` and `og_description` frontmatter. The `og_image` frontmatter key overrides the generated card image.

### Docsmith

If Docsmith is missing a feature this package needs, contribute it upstream in [mrpunyapal/docsmith](https://github.com/MrPunyapal/docsmith) rather than forking the builder.

