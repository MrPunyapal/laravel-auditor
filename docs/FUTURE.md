# Future Scope

These features may influence architecture, but they are **not** part of V1.

## F1: Legacy project / standalone distribution

Audit older Laravel applications without requiring those applications to upgrade PHP or Laravel just to install Auditor.

```text
Modern Laravel project
  -> Composer package

Older Laravel/PHP project
  -> standalone Auditor distribution / external runner
```

## F2: Standalone CLI distribution

A package-runner style command that can audit projects whose runtime is too old for the current package.

```bash
<future-runner> laravel-auditor
```

## F3: Auto-fix / remediation

Turn a verified finding into a proposed fix, tests, and a patch. Any implementation must require explicit user approval and stay read-only by default.

## F4: CI mode

Non-AI or hybrid checks suitable for CI, for example `php artisan auditor:ci`, with fail-on-severity policies, ignored findings, baselines, and JSON/SARIF output.

## F5: Baselines and historical comparison

Accepted or ignored findings, audit baselines, new-vs-existing findings, regressions, and trend reporting.

## F6: More domains

Deployment readiness, observability, queues, caching, API design, frontend quality, accessibility, maintainability, dependency health, configuration management, infrastructure, multi-tenancy, localization, and upgrade readiness.

## F7: Deeper ecosystem rules

Optional rule packs for Livewire, Filament, Inertia, Nova, Pest, PHPUnit, Tailwind, Sanctum, Passport, Horizon, Reverb, Octane, Scout, Pennant, and Vapor. Add a pack only when the rules can stay evidence-first.

## F8: Runtime verification engine

Safe, opt-in checks that turn likely findings into confirmed findings: query measurements, route authorization checks, queue behavior, configuration validation.

## F9: Richer reporting

HTML, SARIF, GitHub annotations, pull request comments, interactive terminal UI, and IDE integration.

## F10: Organization policy packs

Team-defined rule packs such as company security, architecture, or Laravel convention policies.

## F11: Agent profiler / setup diagnostics

Inspect whether an agent has the right skills, guidelines, MCP tools, and instruction hierarchy. Outside Laravel Auditor V1.

## F12: Other framework auditors

The reusable part is the audit methodology. Framework-specific knowledge stays in each auditor.

## Constraints that must survive

1. Agent-agnostic core
2. Boost optional
3. Evidence over guesses
4. Read-only by default
5. Few high-quality rules over noisy volume
6. Framework-aware and version-aware where practical
7. Framework knowledge separate from generic agent infrastructure
8. Standalone/legacy execution possible later without contaminating this package
9. Every finding explainable and reproducible
10. Never pretend an audit is more certain than the evidence
