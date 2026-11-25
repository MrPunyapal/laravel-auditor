---
title: Change review
description: Combine saved Laravel snapshots, migration indicators, and optional audit finding baselines.
order: 14
slug: change-review
---

# Change review

Use the new **read-only** `auditor:review` command to combine existing deterministic evidence in one JSON report:

~~~bash
php artisan auditor:review before.json after.json --output=review.json
~~~

JSON remains the default output. For a human-readable review, select Markdown or terminal text:

~~~bash
php artisan auditor:review before.json after.json --format=markdown --output=review.md
php artisan auditor:review before.json after.json --format=text
~~~

These views include coverage, changed routes and authorization metadata, schema table changes, prioritized structural migration indicators and verification questions, supplied findings and baseline counts, and limitations. Omitted inputs are marked as not reviewed rather than counted as zero. Findings and structural observations remain separate. The JSON report includes the full underlying evidence; the human-readable views are summaries, not a replacement for the machine-readable result. The version 1 envelope and principal result sections are described in `resources/auditor/schema/change-review.schema.json`.

The two inputs are snapshots previously created with `auditor:snapshot` in comparable application environments. The command does **not** switch Git revisions, execute migrations, run an AI coding agent, or collect live state. Review snapshots with the same route-cache state.

The report includes route and authorization changes from the existing snapshot comparator. When **both** snapshots include complete optional database schema sections, it also includes structural migration risk indicators. If neither snapshot includes a schema, the report explicitly marks schema review as not covered. A mismatch or incomplete snapshot fails rather than producing a clean result.

To include a complete current audit's findings and compare them with a historical baseline:

~~~bash
php artisan auditor:review before.json after.json \
  --findings=storage/auditor-findings.json \
  --baseline=storage/auditor-baseline.json \
  --output=storage/change-review.json
~~~

`--findings` accepts the same structured JSON file used by `auditor:report`. `--baseline` requires `--findings`, and baseline interpretation requires equivalent full audit scope across runs. Findings and confidence remain those supplied by the agent or other finding producer. The command does not verify them independently. It protects existing input snapshots, findings, and baseline files from `--output` overwrite.

**Do not interpret an empty comparison as safe.** Structural differences are not automatically vulnerabilities; no indicators do not establish deployment safety. Migration PHP is neither executed nor inspected by this command. For source inspection and database-specific review prompts, use `auditor:migration-risk` with its existing opt-in flags.

A future Git-aware workflow may help orchestrate snapshot and finding artifacts. A `--base` ref alone is not a substitute for capturing the older application's runtime state. See [Context snapshots](snapshots.md), [Migration risk indicators](migration-risk.md), and [Findings and reports](findings.md).
