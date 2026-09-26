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
- Oversized class or long method (`AUD-ARC-006`): one controller action, job `handle()`, Livewire component, or model mixing several responsibilities. Use Extract Method / Extract Class.
- Primitive obsession, data clumps, or long parameter lists (`AUD-ARC-007`): the same 4+ scalars travelling together in two or more places. Use Introduce Parameter Object / Replace Primitive with Object (enum, value object, DTO, form request).
- Adding a variant requires editing core code (`AUD-ARC-008`): copied type-tag switches or hardcoded built-in lists where one new provider, format, or handler touches several files. Use Replace Conditional with Polymorphism (single registry or map; plain array is enough).
- Feature envy, message chains, or inappropriate intimacy (`AUD-ARC-009`): `$order->user->profile->...` chains or repeated reaches into foreign state. Use Move Method / Hide Delegate. Trace the full path and state what happens when an intermediate link is null or unloaded.
- Dead code or speculative generality (`AUD-ARC-010`): unused classes, methods, Blade components, routes, or config keys; abstractions added for a future that never arrived. Delete; do not deprecate unreleased speculation.
- Inconsistent sibling contracts or silent extension defaults (`AUD-ARC-011`): null versus empty, units, ID formats, or error handling differing across siblings; drivers or hooks falling back to silent no-ops for third-party variants. Escalate to `medium` only on a wrong result or hidden failure.
- Tightly coupled components: classes that are hard to test or change because of hidden dependencies.
- Repeated patterns that have become maintenance problems: copy-pasted blocks that should be shared.
- Inconsistent architectural conventions: half the app using one pattern and half another without reason.
- Unnecessary abstractions: interfaces, repositories, or services that add indirection without value.
- Dangerous service/repository patterns: abstraction layers that create complexity rather than value.

## Evidence requirements

Every architecture finding needs at least:

- The specific class(es) and file/line ranges.
- Why the pattern is a problem in this codebase (not just "best practice").
- The concrete consequence: wrong result, missed variant, hidden failure, or the named places a single change must touch.
- The refactoring technique by name (Extract Method, Introduce Parameter Object, Replace Conditional with Polymorphism, Move Method, Hide Delegate) that fits the existing style.
- For `AUD-ARC-010`: grep proof of zero references across source, tests, routes, config, Blade views, and docs, allowing for route or DI auto-wiring, events, policies, jobs, schedules, and string-based lookups.
- For `AUD-ARC-009`/`AUD-ARC-011`: the runtime outcome when layers disagree (null chain, unloaded relation, silent default).

## False positives

- Small controllers that orchestrate a couple of calls are fine.
- A service layer is not automatically bad; only flag it when it adds complexity without value.
- Do not impose a single architecture style the codebase does not otherwise use.
- Fluent Eloquent and query builder chains are idiomatic, not message chains.
- A single switch in one file, a closed set of 2-3 stable cases, or a long cohesive procedure with one reason to change is not a finding.
- Framework conventions (route auto-wiring, policies, gates, listeners, Blade components, jobs) and the public package API count as live callers for dead-code checks.
- Documented migrations between old and new contracts are intentional inconsistency.

## Severity guidance

- `high`: structure actively blocks maintenance or causes bugs (e.g. duplicated critical logic diverging).
- `medium`: clear maintainability problem with real cost.
- `low`: stylistic or mild structure concern.
- `info`: observations for future work.
