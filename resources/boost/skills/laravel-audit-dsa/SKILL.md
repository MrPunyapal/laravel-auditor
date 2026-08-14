---
name: laravel-audit-dsa
description: >
  Run a read-only, orchestrated audit of a Laravel codebase for data
  structures, state representation, algorithms, and ownership. Use when asked
  for a DSA audit, subsystem inventory, or Aaron-style bounded agent audit.
metadata:
  agent: any
---

# Laravel DSA / organizing-model audit

Audit the Laravel application for **materially useful simplifications** in data structures, state representation, control flow, algorithms, and ownership.

This is an **audit-only** exercise. Do not edit files, run mutating commands, implement recommendations, commit, or push. Read-only inspection and Laravel Auditor context tools are allowed.

You are the **coordinator**. Continue until every identifiable subsystem has been reviewed and the final audit is validated.

Credit: this workflow follows Aaron Francis's bounded, read-only subsystem audit method.

## Tools to use first

```bash
php artisan auditor:context subsystems
php artisan auditor:context project_info
php artisan auditor:rules --applicable
```

Also use `routes`, `models`, `database_schema`, `policies_authorization`, `jobs_events_schedules`, and `tests` as needed. Prefer these over raw guesses.

Map each finding to a stable rule when one fits (`AUD-DSA-001`–`AUD-DSA-004`, or a core domain rule). Put `subsystem` and `priority` (`p0`–`p3`) in finding `metadata`.

## 1. Establish the coverage contract

Inspect the repository and inventory every identifiable subsystem. Start from `auditor:context subsystems`, then add any leftover `app/` areas it did not list.

Give each subsystem:

- a stable ID and descriptive name
- an exact ownership boundary
- its key implementation files
- relevant public interfaces, major call sites, and tests
- a status: `queued`, `in review`, `recommend`, or `skip`

Include HTTP, models, auth, database, jobs/queues, events, console, mail/notifications, tests, config, frontend/Livewire/Inertia/Filament when present, and generated-contract ownership where relevant.

Create one canonical report containing:

- the subsystem inventory
- confirmed opportunities
- explicit skip decisions
- cross-cutting patterns
- duplicates and superseded findings
- final priorities and dependencies
- an audit log

Treat this inventory as the coverage contract. Do not assume a catch-all row proves coverage.

## 2. Run bounded subsystem reviews

Use fresh, read-only agents where available. Give every worker **one** distinct subsystem with an exact, non-overlapping ownership boundary.

Keep concurrency bounded to the number of lanes you can actually coordinate. Use one consolidated wait. Do not interrupt productive workers just because they are slow. Close completed workers after harvesting results.

Each worker receives this brief:

> Review the assigned subsystem for **at most two** materially useful simplifications in its data structures, state representation, or organizing model.
>
> Inspect implementation, public interfaces, major call sites, and existing tests. Stay inside the assigned ownership boundary. You may name a cross-subsystem concern; do not expand scope to solve it.
>
> Look for:
>
> - scattered booleans or nullable fields that permit invalid combinations and should become a state machine or discriminated union
> - repeated assumptions about object shape that need a shared typed model
> - duplicated branching that a small map, registry, reducer, or command model would remove
> - unclear state or behavior ownership that a small module boundary would clarify
> - repeated scans, transformations, or lookups where a more appropriate collection or index would materially simplify behavior
> - lifecycle, concurrency, or async states whose representation permits stale or contradictory state
>
> Do not force an abstraction. Prefer boring local code when it is already clear.
>
> Do not recommend changes solely for stylistic consistency, hypothetical extensibility, minor line-count reduction, or moving existing branching behind a new type.
>
> Return at most two opportunities. If nothing clearly meets the threshold, return `skip`.
>
> For every recommendation, provide:
>
> 1. Verdict: recommend or skip
> 2. Evidence with exact file and line references
> 3. Current complexity or invalid states
> 4. Proposed representation and why it is simpler
> 5. Smallest credible implementation scope, including affected files and interfaces
> 6. Regression risks and migration concerns
> 7. Existing and additional validation required
> 8. Confidence: high, medium, or low

## 3. Validate and synthesize

Independently verify every finding against the current repository before accepting it.

Reject, narrow, or demote recommendations that are vague, duplicate another finding, misunderstand intentional Laravel/framework semantics, or merely relocate complexity.

Record skips as completed coverage. Deduplicate overlapping findings and assign each accepted recommendation to one authoritative subsystem.

Continue opening bounded review batches until every inventory row is complete.

## 4. Audit the audit

Before finishing, run fresh independent passes for:

- repository coverage and missing subsystem boundaries
- duplication and ownership overlap
- materiality and over-abstraction
- schema completeness
- dependency-aware priority ranking

If the coverage pass finds a real omission, add an explicit subsystem row and audit it. Do not hide it by broadening a previously completed boundary.

Rank the final recommendations by concrete impact, confidence, implementation effort, blast radius, and prerequisites. Identify the best first implementation slices.

Then assign each accepted finding a tier (put `metadata.priority` on the finding). Every promoted ID appears exactly once.

- **P0** — reachable wrong-record, lost-update, authorization, durable-state, or permanently incomplete-operation risks
- **P1** — concrete boundary failures and high-leverage ownership fixes (less immediate damage, or greater migration cost)
- **P2** — useful invariant improvements with narrower impact or sensitive migration
- **P3** — telemetry / diagnostics / maintainability; keep this tier small after materiality review

Then render:

```bash
php artisan auditor:report --findings=storage/auditor-findings.json
```

The report includes a priority synthesis. Set `metadata.priority` to `p0`–`p3` when you rank explicitly.

## Done only when

- every identifiable subsystem has been reviewed
- every subsystem has a recommendation or explicit skip
- every finding has complete evidence, scope, risk, and validation fields
- duplicates and weak abstractions have been removed
- priorities and dependencies are internally consistent
- the repository remains unchanged
