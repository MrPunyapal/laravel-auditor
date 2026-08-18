# Finding schema

Every audit finding should match the Laravel Auditor finding schema (`schema/finding.schema.json`).

Required fields:

- `id` — unique finding instance id (for example `F-2026-0001`)
- `rule_id` — stable rule id when one matches (for example `AUD-SEC-001`)
- `title`, `domain`, `severity`, `confidence`
- `summary`, `why_it_matters`

Include whenever possible:

- `status` — `open` for new findings (`accepted`, `dismissed`, `fixed` later)
- `evidence` — file, route, symbol, config, migration, query, test, or dependency references
- `affected_resources`, `symbol`
- `recommendation`, optional `remediation`, optional `verification_notes`
- `metadata.priority` — `p0`, `p1`, `p2`, or `p3` when you rank the report
- `metadata.subsystem` — subsystem id on a DSA pass

Minimal shape:

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
  "recommendation": "Authorize the deletion with a PostPolicy or route middleware.",
  "metadata": { "priority": "p0" }
}
```

Write an array of findings (or `{ "findings": [ ... ] }`) to JSON and render:

```bash
php artisan auditor:report --findings=storage/auditor-findings.json
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
```

An example payload lives in `examples/findings.json`. Preview it with:

```bash
php artisan auditor:report --example
```
