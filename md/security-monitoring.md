---
title: Scheduled dependency security checks
description: Check Composer lockfiles for new vulnerability disclosures even when application code has not changed.
og_title: Laravel Auditor security monitoring
og_description: Run scheduled read-only Composer vulnerability checks in GitHub Actions.
order: 12
slug: security-monitoring
---

Laravel Auditor collects dependency audit context on demand. A vulnerability can be disclosed after an application is deployed without any code or lockfile changes. A scheduled lockfile check detects that kind of drift.

## GitHub Actions example

Copy [the example workflow](https://github.com/MrPunyapal/laravel-auditor/blob/main/resources/auditor/examples/composer-security-audit.yml) to `.github/workflows/composer-security-audit.yml` in **your Laravel application repository**. The application must commit `composer.lock`.

The workflow runs daily and on manual dispatch. It uses `composer audit --locked` with Composer 2 to check exact locked versions, including transitive dependencies. It does not need `vendor/`, run Composer plugins, install packages, or change files. GitHub Actions shows a failed job when Composer returns an audit failure; inspect the job logs and advisory links to triage it.

The example runs daily at 05:23 UTC. Scheduled workflows run on the default branch and may start late. Repository Actions settings, plan limits, inactivity, and notification preferences determine whether a run takes place and who is notified.

For production dependencies only, add `--no-dev` to the audit command. Keep development dependencies in scope for the widest supply-chain coverage. Do not add `--direct` unless intentionally excluding transitive packages.

## Limitations

- This is a copyable example. Installing Laravel Auditor does **not** add a GitHub workflow to your application.
- Composer audits dependency policies and vulnerability advisories. It does not prove whether a vulnerable function is reachable in your application.
- A network or advisory-source failure must be investigated; do not report it as a clean result.
- Keep the workflow isolated from deployments. It deliberately has read-only repository permissions and does not run `composer update` or `composer audit fix`.
- An application that uses npm, pnpm, or Yarn needs a separate lockfile-specific check for its JavaScript dependencies.

See the [Composer audit reference](https://getcomposer.org/doc/03-cli.md#audit) and [GitHub Actions schedule documentation](https://docs.github.com/en/actions/reference/workflows-and-actions/events-that-trigger-workflows).
