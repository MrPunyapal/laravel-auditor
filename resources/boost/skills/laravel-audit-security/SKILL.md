---
name: laravel-audit-security
description: >
  Audit a Laravel application's security boundaries: authorization, mass
  assignment, sensitive data, file/URL handling, CSRF, XSS, injection, and
  secrets. Use when auditing security or when asked to review security of a
  Laravel app.
metadata:
  agent: any
---

# Laravel Security Audit

Audit the security boundaries of the Laravel application. **Do not invent vulnerabilities merely because a pattern is uncommon.** Require context and evidence.

List applicable rules first:

```bash
php artisan auditor:rules --domain=security --applicable
```

## What to look for

- Authorization gaps: routes/controllers that mutate or expose resources without a policy, gate, or middleware.
- Insecure resource access: missing ownership checks on update/delete/show.
- Missing policies/gates where the app has clear resource ownership.
- Mass assignment risks: unguarded models or over-permissive `$fillable` combined with direct request input.
- Unsafe validation assumptions: trusting `required`/client-side checks, missing server-side validation on critical fields.
- Sensitive data exposure: secrets or personal data in responses, logs, exceptions, or dumps.
- Unsafe configuration usage: `APP_DEBUG` assumptions, hardcoded credentials, env values echoed.
- Dangerous file handling: unvalidated uploads, paths from user input, unsafe file reads.
- Unsafe URL/redirect handling: open redirects from user-controlled targets.
- Insecure authentication/authorization patterns: storing secrets in plaintext, weak session config, missing rate limits on auth.
- Secrets accidentally committed or exposed where detectable (`.env` committed, keys in source).
- Weak or missing CSRF protection: `VerifyCsrfToken::except` whitelists, removed/reordered CSRF middleware, forms or post endpoints missing the token.
- Unescaped output / XSS risk: Blade `{!! !!}`, `->get()`, `->toHtml()` rendering user-controlled or stored data.
- Raw SQL with user-controlled input: `DB::raw`, `whereRaw`, `selectRaw`, `orderByRaw`, or `statement` interpolating request input.
- Known vulnerable dependencies. `dependencies.composer_audit` is **off by default** — enable `laravel-auditor.context.composer_audit` or run `composer audit --format=json` yourself before reporting `AUD-DEP-001`.
- Queued jobs that serialize Eloquent models with hidden/sensitive attributes (`AUD-QUE-002`).

When these packages are installed, also apply their packs (`auditor:rules --applicable`): Livewire (`AUD-LW-*`), Filament (`AUD-FIL-*`), Inertia (`AUD-IN-*`), Sanctum (`AUD-API-*`).

## Evidence requirements

Every security finding needs at least:

- The specific route/controller/model file and line/range.
- The relevant middleware/policy/gate (or the absence of one).
- What the attacker-controlled input is, when applicable.

## False positives

- Do not claim exploitability without sufficient evidence.
- A missing policy is only a finding when the resource is user-accessible or sensitive.
- Uncommon patterns are not automatically vulnerabilities.
- An empty `composer_audit` payload is not evidence that there are no advisories unless the check actually ran.

## Severity guidance

- `critical`: directly exploitable, e.g. unauthenticated access to sensitive data or arbitrary code execution.
- `high`: requires a real but plausible precondition (e.g. any authenticated user).
- `medium`/`low`: defense-in-depth issues, configuration concerns, or low-exploitability risks.
- `info`: observations worth documenting without immediate risk.
