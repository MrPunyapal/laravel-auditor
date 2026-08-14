---
title: Rules
description: The evidence-first audit catalog and how applicability works.
order: 6
slug: rules
---

Rules are metadata for the reasoning agent. They are not executable scanners.

List them:

```bash
php artisan auditor:rules
php artisan auditor:rules --applicable
```

The authoritative definitions live in `resources/auditor/rules/*.php`. The human catalog is `resources/auditor/rules/RULES.md`.

## Core domains

| Domain | What it looks for |
| --- | --- |
| Security | Authorization, mass assignment, secrets, redirects, file handling, debug exposure |
| Performance | N+1, request-lifecycle work, indexes, queues, cache only when justified |
| Architecture | Boundaries, duplication, unnecessary abstraction — no cargo-cult repositories |
| Database | Relationship/schema mismatch, destructive migrations, missing FKs |
| Testing | Missing meaningful coverage, weak tests, missing authorization tests |
| Conventions | Version-inappropriate APIs, reinvented framework features |

## Ecosystem packs

These rules include `applicability.packages` and stay hidden from `--applicable` when the package is absent:

- Livewire (`AUD-LW-*`)
- Filament (`AUD-FIL-*`)
- Inertia (`AUD-IN-*`)
- Sanctum (`AUD-API-*`)
- Pest (`AUD-PEST-*`)
- Queues (`AUD-QUE-*`)

DSA organizing-model rules (`AUD-DSA-*`) support the `laravel-audit-dsa` skill.

## Writing a rule

Each rule needs a stable ID, domain, severity, confidence, description, why it matters, recommendation, evidence requirements, and false-positive considerations.

Do not add a rule unless it can stay evidence-first. Few high-quality rules beat a noisy catalog.
