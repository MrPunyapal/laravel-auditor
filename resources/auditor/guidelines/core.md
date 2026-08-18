# Laravel Auditor Guidelines

These guidelines apply to any agent performing a Laravel audit with Laravel Auditor. They are intentionally agent-agnostic.

## Purpose

Laravel Auditor equips an AI coding agent with a specialized, evidence-based methodology for auditing Laravel applications. The agent is the reasoning engine; Laravel Auditor provides the methodology, structured context, rules, and tools.

## Working rules

1. **Evidence over guesses.** Every meaningful finding must reference concrete, verifiable project context: a file path and line/range, a symbol, a route, a migration, a query, a configuration key, a dependency, or a test.
2. **Prefer deterministic facts.** When a fact can be obtained programmatically (via the Laravel Auditor context tools or Artisan), obtain it that way rather than inferring it from raw text.
3. **Distinguish confirmed findings from hypotheses.** Use the confidence levels precisely. Only `confirmed` findings have full supporting evidence; everything else is labeled by its actual confidence.
4. **Say when evidence is incomplete.** If you cannot verify a high-severity claim, say so and lower the confidence. Never claim an audit is more certain than the evidence allows.
5. **Do not invent package or runtime behavior.** Verify against installed packages, their documentation, and the actual application code. Never assert behavior you have not checked.
6. **Never claim exploitability without sufficient evidence.** A finding is not automatically exploitable because the pattern is unusual.
7. **Do not recommend upgrades solely because a package is old.** Only flag a dependency when there is a concrete reason (breaking incompatibility, known vulnerability with evidence, EOL with operational impact).
8. **Style preferences are never high severity.** Keep them at `low` or `info`, or omit them.
9. **Read-only by default.** Auditing must never modify application code.
10. **Few high-quality rules over noisy volume.** A focused, trustworthy report is the product.

## Severity and confidence

- **Severity**: `critical`, `high`, `medium`, `low`, `info`.
- **Confidence**: `confirmed`, `high`, `medium`, `low`.

Use both precisely. A `confirmed` finding has verified evidence. A `high` severity claim with only partial evidence should carry `medium` or `low` confidence.

## Structured findings

Each finding should carry, at minimum:

- `rule_id` — stable rule ID when one matches (e.g. `AUD-SEC-001`).
- `id`, `rule_id`, `title`, `domain`, `severity`, `confidence`, `status`.
- `summary`, `why_it_matters`.
- `evidence` — concrete references.
- `affected_resources`, `symbol` — where relevant.
- `recommendation`, optional `remediation`, optional `verification_notes`.
- `metadata.priority` — `p0`–`p3` when you rank findings.

Write findings to JSON and render them with `php artisan auditor:report --findings=storage/auditor-findings.json`. See `guidelines/findings.md`.

## Using the tools

The Laravel Auditor context tools provide deterministic Laravel context:

- `project_info`, `routes`, `models`, `migrations`, `database_schema`.
- `dependencies`, `configuration`, `policies_authorization`, `jobs_events_schedules`, `tests`, `subsystems`.

List them with `php artisan auditor:context --list`. Prefer `php artisan auditor:rules --applicable` before investigating ecosystem-specific issues.

`dependencies.composer_audit` and `tests` case listing are **off by default**. Do not treat a missing advisory list or file-count test total as proof the check ran. Enable `laravel-auditor.context.composer_audit` / `test_listing`, or run `composer audit --format=json` / the test runner yourself.

Prefer these tools over raw file scraping for structured facts. Use file inspection for code-level detail and tracing.

Useful commands: `auditor:status`, `auditor:rules`, `auditor:context`, `auditor:report`, `auditor:ci`, `auditor:mcp`.

## When Boost is present

Laravel Auditor extends Laravel Boost rather than replacing it. Continue using Boost's Laravel documentation search and general context. Use Auditor's audit-specific skills, rules, and context tools for the audit work itself. Do not duplicate what Boost already provides.
