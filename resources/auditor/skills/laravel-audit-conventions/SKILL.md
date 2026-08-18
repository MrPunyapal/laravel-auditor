---
name: laravel-audit-conventions
description: >
  Audit a Laravel application for framework conventions: anti-patterns,
  version-appropriate APIs, and misuse of framework features. Use when
  auditing Laravel conventions.
metadata:
  agent: any
---

# Laravel Conventions Audit

Audit the application for Laravel framework conventions. **The rules must be version-aware whenever the installed version is known.**

List applicable rules first:

```bash
php artisan auditor:rules --domain=conventions --applicable
```

## What to look for

- Framework anti-patterns: patterns that fight the framework's intended flow.
- Unnecessary custom code where standard Laravel mechanisms are clearly better: custom auth loops instead of guards, manual pagination instead of `paginate`, custom file handling instead of storage, etc.
- Version-inappropriate APIs when the version is known: deprecated or removed methods, old-style helpers, `facades` used in ways removed in the installed version.
- Incorrect framework assumptions: `config()` keys that don't exist, middleware that isn't applied, routes that don't match registered patterns.
- Misuse of framework lifecycle/features: service providers doing work in `register()`, observers/mutators causing side effects, cache keys without invalidation, queueable misuse, scheduling misconfig.
- Configuration, validation, routing, jobs, events, policies, resources, commands, notifications, mail, queues, scheduling, and caching used in problematic ways.
- Abandoned runtime dependencies (`AUD-DEP-002`) or license conflicts for a commercial product (`AUD-DEP-003`) — only with `composer show` / license evidence, not because a package is old.

## Evidence requirements

Every conventions finding needs at least:

- The installed Laravel version (from `project_info`).
- The code in question (file + line/range).
- The idiomatic Laravel alternative and why it is better.

## False positives

- Custom code may exist for good reasons the framework mechanism cannot serve — verify before recommending replacement.
- Only recommend replacement when the standard mechanism is clearly better.
- Do not flag style preferences as findings.

## Severity guidance

- `high`: usage that is broken or will break on upgrade.
- `medium`: clear anti-pattern with maintenance cost.
- `low`: minor convention drift.
- `info`: observations.
