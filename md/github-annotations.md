---
title: GitHub Actions annotations
description: Surface verified findings on GitHub pull requests without a hosted review bot.
order: 12
slug: github-annotations
---

# GitHub Actions annotations

Laravel Auditor can emit GitHub Actions workflow commands from **existing structured findings**, alongside Markdown, JSON, text, and SARIF output.

~~~bash
php artisan auditor:report --findings=storage/auditor-findings.json --format=github
php artisan auditor:ci --findings=storage/auditor-findings.json --format=github --fail-on=high
~~~

When run inside GitHub Actions, annotations appear in the check output. The new format escapes special characters, omits unsafe file paths, and skips resolved findings. It does **not** call GitHub APIs or generate findings itself.

Use the existing base/dirty options to scope findings to changed files before emitting annotations. The reporter does not claim that a finding was automatically verified.
