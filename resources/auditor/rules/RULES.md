# Laravel Auditor Rule Reference

This reference documents the V1 audit rules. Each rule maps to a stable rule ID used in findings. The authoritative definitions live as PHP files in `resources/auditor/rules/`; `php artisan auditor:rules` lists them with full metadata.

The V1 rules intentionally favor a smaller, trustworthy set over volume. See the `RuleDefinition` model for the full schema.

## Security

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-SEC-001 | Missing authorization boundary | high | high |
| AUD-SEC-002 | Mass assignment risk | high | medium |
| AUD-SEC-003 | Sensitive data exposure | high | medium |
| AUD-SEC-004 | Unsafe redirect to user-controlled URL | medium | high |
| AUD-SEC-005 | Dangerous file handling | high | medium |
| AUD-SEC-006 | Secrets committed or hardcoded | high | high |
| AUD-SEC-007 | Risky debug or error exposure | medium | high |
| AUD-SEC-008 | Unsafe validation assumptions | medium | medium |
| AUD-SEC-009 | Unsafe configuration usage | medium | high |
| AUD-SEC-010 | Insecure authentication pattern | high | medium |

## Performance

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-PER-001 | N+1 query risk | medium | medium |
| AUD-PER-002 | Expensive work in the request lifecycle | low | medium |
| AUD-PER-003 | Missing index on a frequently-queried column | low | low |
| AUD-PER-004 | Repeated query without reuse | low | medium |
| AUD-PER-005 | Synchronous work that belongs on a queue | medium | medium |
| AUD-PER-006 | Inefficient collection or database usage | low | medium |
| AUD-PER-007 | Repeated expensive computation without cache | low | low |

## Architecture

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-ARC-001 | Business logic in controllers | medium | medium |
| AUD-ARC-002 | Duplicated business logic | medium | high |
| AUD-ARC-003 | Unnecessary abstraction | low | medium |
| AUD-ARC-004 | Application boundary violation | medium | medium |
| AUD-ARC-005 | Inconsistent architectural convention | low | medium |

## Database

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-DB-001 | Relationship definition inconsistent with schema | medium | medium |
| AUD-DB-002 | Suspicious or destructive migration | high | medium |
| AUD-DB-003 | Nullable/non-nullable mismatch | low | medium |
| AUD-DB-004 | Missing foreign key | medium | medium |
| AUD-DB-005 | Duplicate data modeling | low | medium |
| AUD-DB-006 | Inefficient relationship usage | low | medium |

## Testing

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-TST-001 | Critical business flow without test coverage | medium | low |
| AUD-TST-002 | Tests that do not verify meaningful behavior | low | medium |
| AUD-TST-003 | Missing authorization tests | medium | low |
| AUD-TST-004 | Important edge cases left untested | low | low |
| AUD-TST-005 | Brittle or inconsistent test conventions | low | medium |

## Laravel conventions

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-CON-001 | Version-inappropriate API usage | medium | high |
| AUD-CON-002 | Reinventing framework functionality | medium | high |
| AUD-CON-003 | Misuse of framework lifecycle or features | low | medium |
| AUD-CON-004 | Framework anti-pattern | medium | medium |
| AUD-CON-005 | Incorrect framework assumption | medium | high |
| AUD-CON-006 | Validation or form-request convention gap | low | medium |

## Ecosystem packs

These rules only apply when the package is installed (`applicability.packages`). List them with `php artisan auditor:rules --applicable`.

### Livewire (`livewire/livewire`)

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-LW-001 | Livewire action missing authorization | high | high |
| AUD-LW-002 | Unvalidated Livewire public property | high | medium |

### Filament (`filament/filament`)

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-FIL-001 | Filament resource missing policy | high | medium |
| AUD-FIL-002 | Unrestricted Filament bulk action | high | medium |

### Inertia (`inertiajs/inertia-laravel`)

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-IN-001 | Inertia shared data leaks sensitive attributes | high | medium |
| AUD-IN-002 | Inertia endpoint missing authorization | high | high |

### Queues

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-QUE-001 | Job missing retry or timeout bounds | medium | medium |
| AUD-QUE-002 | Sensitive model serialized on the queue | medium | medium |
| AUD-QUE-003 | Sync queue driver in a non-local environment | medium | high |

### API / Sanctum (`laravel/sanctum`)

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-API-001 | Mutating API route missing token auth | high | high |
| AUD-API-002 | Overly broad API token abilities | medium | medium |

### Pest (`pestphp/pest`)

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-PEST-001 | PHPUnit class tests in a Pest-first suite | low | low |
