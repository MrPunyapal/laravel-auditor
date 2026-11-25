---
title: Change review
description: Combine saved Laravel snapshots, migration indicators, and optional audit finding baselines.
order: 14
slug: change-review
---

# Change review

Use the **read-only** `auditor:review` command to combine saved evidence in one report:

~~~bash
php artisan auditor:review before.json after.json --output=review.json
~~~

JSON remains the default output. For a human-readable review, select Markdown or terminal text:

~~~bash
php artisan auditor:review before.json after.json --format=markdown --output=review.md
php artisan auditor:review before.json after.json --format=text
~~~

These views include coverage, route and authorization changes, schema changes, migration indicators, supplied findings, and baseline comparisons. Baseline details show severity increases and reopens when the historical status is known. Omitted evidence is marked as unavailable. JSON includes the full evidence and follows the version 1 schema in `resources/auditor/schema/change-review.schema.json`.

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

**Do not interpret an empty comparison as safe.** Structural differences are not automatically vulnerabilities; no indicators do not establish deployment safety. Migration PHP is never executed. With complete schema snapshots on both sides, opt in to the existing token-based source inspector and conditional driver guidance:

~~~bash
php artisan auditor:review before.json after.json --migration=database/migrations/example.php --driver-guidance
~~~

Select actual registered migration paths (repeatable, at most 50). The report keeps structural indicators distinct from source indicators and driver questions. The structural indicator count does not include source occurrences. These flags require complete schema-enabled snapshots; no migration execution, measured lock duration, or deployment-safety guarantee is implied.

## Git-aware review

With Git installed, capture snapshots with `--with-provenance` at the merge base and the head commit in separate trusted, comparable environments. Store the artifacts outside the working tree or in ignored storage. From the clean head checkout:

~~~bash
php artisan auditor:review before.json after.json --base=origin/main --format=markdown
~~~

The command validates the recorded clean revisions against a unique local merge base and HEAD, rejects missing/truncated Git scope or dirty captures, and includes the changed-file list. It never checks out, installs, or boots a historical revision. The required snapshot files remain explicit; a Git ref cannot recreate runtime state. Matching unsigned metadata is not proof of authenticity. Existing full-scope findings/baselines remain unfiltered. See [Context snapshots](snapshots.md), [Migration risk indicators](migration-risk.md), and [Findings and reports](findings.md).

## GitHub Actions

Copy the [manual change-review workflow](https://github.com/mrpunyapal/laravel-auditor/blob/main/resources/auditor/examples/change-review.yml) into your application's `.github/workflows/` directory. Use a package version that includes Git-aware review and status-aware baselines. Follow the trusted capture and privacy requirements in [GitHub Actions annotations](github-annotations.md).
