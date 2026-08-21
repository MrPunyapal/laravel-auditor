---
title: Rules
description: The evidence-first audit catalog and how applicability works.
og_title: Laravel Auditor rules
og_description: The evidence-first audit catalog across security, performance, architecture, database, testing, and conventions.
order: 7
slug: rules
---

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
