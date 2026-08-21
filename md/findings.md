---
title: Findings and reports
description: Finding schema, example payloads, and how reports are rendered.
og_title: Findings and reports
og_description: The finding schema, severity and confidence model, P0–P3 ranking, and how reports are rendered.
order: 8
slug: findings
---

Laravel Auditor does not invent findings. The agent produces them; the package stores, validates, and renders them.

This separation is deliberate. The agent reasons about the code and produces structured findings. Laravel Auditor validates those findings against its schema and renders them as reports. The package never decides what is a vulnerability — the agent does.

## Finding lifecycle

```text
AI agent
   ↓
reads code, applies rules, gathers evidence
   ↓
creates finding (JSON)
   ↓
Auditor loads and validates the finding
   ↓
Auditor renders it as Markdown, JSON, text, or SARIF
```

## Finding schema

See `resources/auditor/schema/finding.schema.json` and `resources/auditor/examples/findings.json`.

Required fields:

| Field | Purpose |
| --- | --- |
| `id` | Unique finding instance ID (e.g. `F-2026-0001`) |
| `title` | Short, specific description |
| `domain` | Audit domain (`security`, `performance`, `architecture`, `database`, `testing`, `conventions`) |
| `severity` | Impact level |
| `confidence` | How certain the finding is given available evidence |
| `summary` | What is wrong |
| `why_it_matters` | Why it matters for this application |

Optional fields:

| Field | Purpose |
| --- | --- |
| `rule_id` | Stable rule ID when one matches (e.g. `AUD-SEC-001`). Omit it when no catalog rule applies; unmapped findings render with an `unmapped` rule label |

Include whenever possible:

| Field | Purpose |
| --- | --- |
| `evidence` | Concrete references (file paths, lines, routes, symbols, config keys) |
| `affected_resources` | Files, routes, config keys involved |
| `symbol` | Class, method, or route reference |
| `recommendation` | What to do about it |
| `remediation` | Optional step-by-step fix guidance |
| `verification_notes` | How the finding was verified, or why it could not be |

Optional `metadata`:

- `subsystem` — inventory ID from `auditor:context subsystems`
- `priority` — `p0`, `p1`, `p2`, or `p3`

## Severity and confidence

These are separate dimensions.

**Severity** describes impact: how much damage a confirmed instance could cause.

**Confidence** describes certainty: how well the evidence supports the claim.

A potentially critical issue with thin evidence should carry low confidence. A confirmed issue with verified evidence carries `confirmed` confidence. This distinction prevents the agent from inflating findings without proof.

- **Severity**: `critical`, `high`, `medium`, `low`, `info`
- **Confidence**: `confirmed`, `high`, `medium`, `low`

## Priority tiers

Reports assign each finding a priority tier (P0-P3). If `metadata.priority` is not set, Auditor derives it:

| Tier | Meaning | Auto-assigned when |
| --- | --- | --- |
| P0 | Correctness, security, or data-loss risk | Critical severity, or high severity in security/database |
| P1 | Concrete correctness or high-leverage work | High severity, or medium severity in security |
| P2 | Material invariant improvements | Medium severity |
| P3 | Telemetry, diagnostics, maintainability | Low or info severity |

Every promoted ID appears exactly once in the priority synthesis.

## Example finding

```json
{
  "id": "F-2026-0001",
  "rule_id": "AUD-SEC-001",
  "title": "Missing authorization boundary",
  "domain": "security",
  "severity": "high",
  "confidence": "confirmed",
  "status": "open",
  "summary": "Any authenticated user can delete another user's post.",
  "why_it_matters": "The destroy action never authorizes the Post policy.",
  "evidence": [
    {
      "type": "file",
      "reference": "app/Http/Controllers/PostController.php",
      "line": 42,
      "end_line": 48
    }
  ],
  "affected_resources": ["app/Http/Controllers/PostController.php"],
  "symbol": "App\\Http\\Controllers\\PostController@destroy",
  "recommendation": "Authorize the deletion with a PostPolicy or route middleware."
}
```

## Reports

The agent writes findings as JSON. Auditor renders them:

```bash
php artisan auditor:report --findings=storage/auditor-findings.json --format=markdown
php artisan auditor:report --findings=storage/auditor-findings.json --format=json
php artisan auditor:report --findings=storage/auditor-findings.json --format=text
php artisan auditor:report --findings=storage/auditor-findings.json --format=sarif
php artisan auditor:report --example
```

Reports include project facts, severity and domain counts, a priority synthesis, key risks, and all findings with evidence and recommendations.

## CI gating

```bash
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high --format=sarif --output=auditor.sarif
```

CI fails when an open finding meets or exceeds the `--fail-on` threshold.
