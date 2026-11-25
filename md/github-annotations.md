---
title: GitHub Actions annotations
description: Surface structured audit findings in GitHub Actions without a hosted review bot.
order: 12
slug: github-annotations
---

# GitHub Actions annotations

Laravel Auditor can emit GitHub Actions workflow commands from **existing structured findings**, alongside Markdown, JSON, text, and SARIF output.

~~~bash
php artisan auditor:report --findings=storage/auditor-findings.json --format=github
php artisan auditor:ci --findings=storage/auditor-findings.json --format=github --fail-on=high
~~~

When run inside GitHub Actions, annotations appear in the check output. The new format escapes special characters, omits unsafe file paths, and skips findings whose status is not `open`. It does **not** call GitHub APIs or generate findings itself.

Use the existing base/dirty options to scope findings to changed files before emitting annotations. The reporter does not independently verify findings or their severity.

## Review trusted before/after artifacts in CI

The package includes [change-review.yml](https://github.com/mrpunyapal/laravel-auditor/blob/main/resources/auditor/examples/change-review.yml), a manual workflow example for the Git-aware review and regression-aware finding policy. It uses the existing `auditor:review`, `auditor:ci`, and optional annotation renderer rather than implementing another scanner.

1. Install the package version supporting these commands in the application and commit its lockfile. Configure a disposable testing environment that can boot the application. Adapt PHP and extension requirements as needed; the example assumes PHP 8.4.
2. In separate **trusted** capture runs at the exact merge base and head commit, capture comparable snapshots using `auditor:snapshot --with-provenance`. Include `--schema` on both sides only when you have equivalent disposable database environments. Capture from a clean working tree, writing artifacts outside it.
3. Produce complete findings files with stable IDs and equivalent audit scope. At the merge base, create the baseline using `auditor:baseline --with-status` if the regression gate is enabled. Do not create the baseline from the current findings.
4. Upload an artifact named `laravel-auditor-before` containing `snapshot.json` and `baseline.json` at its root, and one named `laravel-auditor-after` containing `snapshot.json` and `findings.json` at its root. Record the two successful workflow run IDs in the same repository.
5. Copy the example into the application's default branch and manually dispatch it on the **trusted** head revision with the base ref and both run IDs.

The example verifies the capture runs' recorded repository, commit and successful conclusion before downloading artifacts using a read-only token. This checks association, not authenticity or correctness: independently trust the producer workflows, capture environments and stored artifacts. The workflow deliberately does not use an automatic untrusted PR trigger, check out historical code, run migrations, or generate findings. Composer installation and Artisan still execute the selected application's trusted code. Never use production credentials or attach production data.

The evidence review is a separate step from the finding gate. The latter defaults to new, severity-increased and reopened open findings at high severity or above, using a version 2 baseline. Turn off the regression option to use the backward-compatible new-only policy. Neither policy treats structural indicators as confirmed vulnerabilities; a failed or unavailable evidence step does not become a passing gate.

The job summary contains counts and fixed labels. Full reports and command diagnostics remain in the runner's temporary directory. Enable annotations only after reviewing the confidentiality of finding summaries, file paths and recommendations. Use `auditor:ci --format=sarif --output=...` to create SARIF for private local or restricted artifact use.

See GitHub's [artifact download documentation](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/download-workflow-artifacts) and [workflow permissions](https://docs.github.com/en/actions/reference/workflows-and-actions/workflow-syntax#permissions) when adapting the example.
