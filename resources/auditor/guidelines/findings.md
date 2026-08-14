# Finding schema

Every audit finding should match the Laravel Auditor finding schema (`schema/finding.schema.json`).

Required fields:

- `id` — unique finding instance id (for example `F-2026-0001`)
- `rule_id` — stable rule id when one matches (for example `AUD-SEC-001`)
- `title`, `domain`, `severity`, `confidence`
- `summary`, `why_it_matters`

Include whenever possible:

- `evidence` — file, route, symbol, config, migration, query, test, or dependency references
- `affected_resources`, `symbol`
- `recommendation`, optional `remediation`, optional `verification_notes`

Write findings to JSON and render them with:

```bash
php artisan auditor:report --findings=storage/auditor-findings.json
```

An example payload lives in `examples/findings.json`. Preview it with:

```bash
php artisan auditor:report --example
```
