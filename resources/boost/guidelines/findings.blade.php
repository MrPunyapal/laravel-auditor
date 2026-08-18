## Laravel Auditor findings

Write structured findings, not a chat-only review. Match `schema/finding.schema.json`.

Required: `id`, `rule_id`, `title`, `domain`, `severity`, `confidence`, `summary`, `why_it_matters`.

Include whenever possible: `status` (`open` for new findings), `evidence`, `affected_resources`, `symbol`, `recommendation`, `remediation`, `verification_notes`, and `metadata.priority` (`p0`–`p3`).

Write JSON to `storage/auditor-findings.json` and render:

```bash
php artisan auditor:report --findings=storage/auditor-findings.json
php artisan auditor:ci --findings=storage/auditor-findings.json --fail-on=high
```

Preview the packaged example with `php artisan auditor:report --example`.
