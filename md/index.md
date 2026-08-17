---
title: Laravel Auditor
description: Evidence-based, agent-agnostic auditing tools and methodology for Laravel applications.
og_title: Laravel Auditor — Evidence-based auditing for Laravel
og_description: Equip your AI agent with a repeatable audit workflow, deterministic context tools, and evidence-first findings.
order: 1
slug: index
sidebar_label: Overview
---

Laravel Auditor gives an AI coding agent the specialized knowledge, structured context, and repeatable methodology it needs to audit a Laravel application well.

It is **not** an autonomous AI service, a generic code-review prompt, or a replacement for Laravel Boost. The AI agent remains the reasoning engine. Laravel Auditor provides the audit workflow, domain knowledge, rules, finding schema, and structured Laravel context tools.

## Why it exists

AI agents can already read a Laravel codebase. Ask one to "audit my app" without specialized methodology and you will likely get:

- **Guesses about framework behavior** instead of collected project facts. The agent assumes how a package works rather than checking the installed version and its actual configuration.
- **Style opinions reported as high-severity issues**. The agent flags a naming convention it dislikes as a security risk.
- **Invented vulnerabilities from uncommon patterns**. The agent reports a theoretical attack vector that does not apply to this application's actual setup.
- **Serious findings reported without verification**. The agent claims a missing authorization boundary without checking the routes, middleware, or policies.

Laravel Auditor fixes this by giving the agent a repeatable audit workflow and deterministic project context. Findings stay specific, evidenced, and trustworthy.

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

Laravel developers and teams who already use an AI coding agent and want a more systematic, evidence-driven way to audit an application. If you have ever asked an agent to "review my Laravel app" and received a noisy, unhelpful list of opinions, this package is for you.

## Quick start

```bash
composer require --dev mrpunyapal/laravel-auditor
```

Then either:

```bash
# With Laravel Boost installed:
php artisan boost:install

# Without Boost:
php artisan auditor:install
```

Open your AI agent and ask:

> Use the laravel-audit skill to audit this application. Discover the project first, scope the relevant domains, and report only evidenced findings.

The agent follows the skill workflow, uses the context tools to gather facts, applies the rules, and produces structured findings with evidence.

## Next

- [Installation](/installation/) — install and wire the package
- [Usage](/usage/) — commands, workflow, and reporting
- [Agent setup](/agents/) — connect to your specific AI agent
