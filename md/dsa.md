---
title: DSA audit
description: Bounded, read-only subsystem audit for data structures, state, algorithms, and ownership.
og_title: DSA audit — bounded subsystem review
og_description: A bounded, read-only subsystem audit for data structures, state, algorithms, and ownership.
order: 8
slug: dsa
---

The `laravel-audit-dsa` skill is a Laravel-native version of a bounded, read-only coordinator audit (the method Aaron Francis described: inventory → fresh workers → validate, dedupe, rank).

This is **audit only**. Do not edit files, implement fixes, commit, or push.

## Flow

1. **Coverage contract** — inventory every subsystem (`php artisan auditor:context subsystems`), then add leftover `app/` areas.
2. **Bounded reviews** — one worker per ownership boundary. At most **two** material simplifications, or `skip`.
3. **Validate** — the coordinator verifies every finding against the repo. Reject vague, duplicate, or complexity-relocating ideas.
4. **Audit the audit** — coverage, overlap, over-abstraction, then rank.

## What workers look for

- Invalid boolean/nullable combinations that should be a state
- Repeated object-shape assumptions
- Copied switches a small registry would remove
- Unclear ownership of state or behavior
- Repeated scans that need an index
- Lifecycle/async representations that allow stale or contradictory state

Do not force an abstraction. Prefer boring local code when it is already clear.

## Ranking

Rank by impact, confidence, effort, blast radius, and prerequisites. Then assign:

| Tier | Meaning |
| --- | --- |
| P0 | Reachable wrong-record, lost-update, authorization, durable-state, or incomplete-operation risk |
| P1 | Concrete boundary / high-leverage ownership fixes |
| P2 | Useful invariants, narrower impact or sensitive migration |
| P3 | Telemetry / diagnostics / maintainability — keep this small |

Every promoted ID appears exactly once.

```bash
php artisan auditor:context subsystems
php artisan auditor:report --findings=storage/auditor-findings.json
```
