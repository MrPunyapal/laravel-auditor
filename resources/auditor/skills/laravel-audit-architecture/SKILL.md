---
name: laravel-audit-architecture
description: >
  Audit a Laravel application's architecture: boundaries, responsibility,
  coupling, duplication, and maintainability. Use when auditing architecture.
metadata:
  agent: any
---

# Laravel Architecture Audit

Audit the architecture of the Laravel application. **Avoid cargo-cult recommendations** such as "create a repository for every model" or "move everything into services."

List applicable rules first:

```bash
php artisan auditor:rules --domain=architecture --applicable
```

## What to look for

- Clear violations of application boundaries: domain logic leaking into controllers, routes, or views; persistence concerns in presentation layers.
- Duplicated business logic: the same rule implemented in several places with divergence risk.
- Excessive controller responsibility: controllers doing far more than orchestrating HTTP.
- Business logic in inappropriate layers: rules embedded in views, middleware, or migrations.
- Tightly coupled components: classes that are hard to test or change because of hidden dependencies.
- Repeated patterns that have become maintenance problems: copy-pasted blocks that should be shared.
- Inconsistent architectural conventions: half the app using one pattern and half another without reason.
- Unnecessary abstractions: interfaces, repositories, or services that add indirection without value.
- Dangerous service/repository patterns: abstraction layers that create complexity rather than value.

## Evidence requirements

Every architecture finding needs at least:

- The specific class(es) and file/line ranges.
- Why the pattern is a problem in this codebase (not just "best practice").
- A concrete improvement that fits the existing style.

## False positives

- Small controllers that orchestrate a couple of calls are fine.
- A service layer is not automatically bad; only flag it when it adds complexity without value.
- Do not impose a single architecture style the codebase does not otherwise use.

## Severity guidance

- `high`: structure actively blocks maintenance or causes bugs (e.g. duplicated critical logic diverging).
- `medium`: clear maintainability problem with real cost.
- `low`: stylistic or mild structure concern.
- `info`: observations for future work.
