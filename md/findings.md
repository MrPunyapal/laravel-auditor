---
title: Findings and reports
description: Finding schema, example payloads, and how reports are rendered.
order: 7
slug: findings
---

Laravel Auditor does not invent findings. The agent produces them; the package stores, validates, and renders them.

## Finding JSON

See `resources/auditor/schema/finding.schema.json` and `resources/auditor/examples/findings.json`.

Required fields: `id`, `rule_id`, `title`, `domain`, `severity`, `confidence`, `summary`, `why_it_matters`.

Include whenever possible: `evidence`, `affected_resources`, `symbol`, `recommendation`, `remediation`, `verification_notes`.

Optional `metadata`:

- `subsystem` — inventory ID from `auditor:context subsystems`
- `priority` — `p0`, `p1`, `p2`, or `p3`

## Severity and confidence

- Severity: `critical`, `high`, `medium`, `low`, `info`
- Confidence: `confirmed`, `high`, `medium`, `low`

A high-severity claim with thin evidence should carry medium or low confidence.

## Priority tiers

If `metadata.priority` is omitted, Auditor derives a tier:

- **P0** — critical, or high security/database
- **P1** — other high, or medium security
- **P2** — medium
- **P3** — low / info

Reports print a **Priority synthesis** so every promoted ID appears once.

## Commands

```bash
php artisan auditor:report --findings=storage/auditor-findings.json --format=markdown
php artisan auditor:report --example
php artisan auditor:report --findings=storage/auditor-findings.json --format=sarif
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
```
