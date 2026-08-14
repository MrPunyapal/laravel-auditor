## Laravel Auditor

Laravel Auditor equips an AI coding agent with a specialized, evidence-based methodology and toolset for auditing Laravel applications.

### Relationship to Boost

Laravel Auditor extends Laravel Boost. It does **not** replace Boost's general Laravel context. Continue using Boost's documentation search and general Laravel guidelines. Use Laravel Auditor when asked to audit, review, or assess an existing Laravel application.

### What Laravel Auditor provides

- A structured audit workflow: **Discover** project facts, **Scope** relevant domains, **Investigate** with evidence, **Verify** high-severity findings, and **Report** structured findings.
- Six audit domains: security, performance, architecture, database, testing, and Laravel conventions.
- Stable audit rules with rule IDs (e.g. `AUD-SEC-001`), severity, confidence, evidence requirements, and false-positive considerations.
- Context tools that expose deterministic Laravel facts: project info, routes, models, migrations, database schema, dependencies, configuration, authorization, jobs/events/schedules, and tests.
- A finding schema: rule ID, title, domain, severity, confidence, status, summary, why-it-matters, evidence, affected resources, recommendation, remediation, and verification notes.

### Installing

```bash
composer require --dev mrpunyapal/laravel-auditor
php artisan boost:install
```

When Boost is not installed, use the package's own installer instead:

```bash
php artisan auditor:install
```

Useful commands: `auditor:status`, `auditor:rules`, `auditor:report`, and `auditor:mcp`.

### Audit skill

Use the `laravel-audit` skill when asked to audit or review a Laravel application. It contains the full workflow, evidence requirements, and severity/confidence guidance. Domain skills (`laravel-audit-security`, `laravel-audit-performance`, `laravel-audit-architecture`, `laravel-audit-database`, `laravel-audit-testing`, `laravel-audit-conventions`) go deeper once the scope is chosen.

### Key rules

- Evidence first: every meaningful finding cites concrete file paths, lines, routes, or config keys.
- Prefer deterministic project facts over model guesses.
- Distinguish confirmed findings from hypotheses.
- Never claim exploitability without sufficient evidence.
- Read-only by default: never modify application code during an audit.
- Few high-quality findings over noisy volume.
