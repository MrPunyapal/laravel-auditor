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

Keep a finding ID stable when it represents the same issue across runs. To gate only new findings, create a baseline from a complete findings file:

```bash
php artisan auditor:baseline --findings=storage/auditor-findings.json --output=storage/auditor-baseline.json
php artisan auditor:ci --findings=storage/auditor-findings.json --baseline=storage/auditor-baseline.json --fail-on=high
```

Use the same full audit scope for both files. An ID absent from the current findings file does not prove the issue was fixed. Baseline mode cannot be combined with `--base` or `--dirty`.
