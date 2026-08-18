---
title: DSA audit
description: Bounded, read-only subsystem audit for data structures, state, algorithms, and ownership.
og_title: DSA audit — bounded subsystem review
og_description: A bounded, read-only subsystem audit for data structures, state, algorithms, and ownership.
order: 8
slug: dsa
---

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
