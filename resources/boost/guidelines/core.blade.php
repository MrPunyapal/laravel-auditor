## Laravel Auditor

Laravel Auditor equips an **existing** AI coding agent with an evidence-based Laravel audit methodology. It does not scan the app by itself. Use it when the user asks to audit, review, or assess a Laravel application.

### Relationship to Boost

Laravel Auditor extends Laravel Boost. It does **not** replace Boost's general Laravel context. Continue using Boost's documentation search and general Laravel guidelines. Use Laravel Auditor when asked to audit, review, or assess an existing Laravel application.

### What Laravel Auditor provides

- A structured audit workflow: **Discover** project facts, **Scope** relevant domains, **Investigate** with evidence, **Verify** high-severity findings, and **Report** structured findings.
- Six audit domains: security, performance, architecture, database, testing, and Laravel conventions.
- Stable audit rules with rule IDs (e.g. `AUD-SEC-001`), severity, confidence, evidence requirements, and false-positive considerations.
- Context tools that expose deterministic Laravel facts: project info, routes, models, migrations, database schema, dependencies, configuration, authorization, jobs/events/schedules, tests, and subsystems.
- A finding schema: id, rule ID, title, domain, severity, confidence, status, summary, why-it-matters, evidence, affected resources, recommendation, remediation, verification notes, and optional `metadata.priority` (`p0`–`p3`).

### Installing

```bash
composer require --dev mrpunyapal/laravel-auditor
php artisan boost:install
```

When Boost is not installed, use the package's own installer instead:

```bash
php artisan auditor:install --agents=claude_code
```

Useful commands: `auditor:status`, `auditor:rules` (`--applicable`), `auditor:context`, `auditor:report`, `auditor:ci`, and `auditor:mcp`.

`composer audit` is **on by default**. Test-case listing is **off by default**. Do not treat `composer_audit.available: false` or an empty advisory list as “no vulnerabilities” unless the check actually ran.

### Audit skill

Use the `laravel-audit` skill when asked to audit or review a Laravel application. It contains the full workflow, evidence requirements, and severity/confidence guidance. Domain skills (`laravel-audit-security`, `laravel-audit-performance`, `laravel-audit-architecture`, `laravel-audit-database`, `laravel-audit-testing`, `laravel-audit-conventions`) go deeper once the scope is chosen. Use `laravel-audit-dsa` for a bounded subsystem / data-structure / ownership audit (`auditor:context subsystems`, P0–P3 ranking).

### Key rules

- Evidence first: every meaningful finding cites concrete file paths, lines, routes, or config keys.
- Prefer deterministic project facts over model guesses.
- Distinguish confirmed findings from hypotheses.
- Never claim exploitability without sufficient evidence.
- Read-only by default: never modify application code during an audit.
- Few high-quality findings over noisy volume.
