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

## Performance

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-PER-001 | N+1 query risk | medium | medium |
| AUD-PER-002 | Expensive work in the request lifecycle | low | medium |
| AUD-PER-003 | Missing index on a frequently-queried column | low | low |

## Architecture

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-ARC-001 | Business logic in controllers | medium | medium |
| AUD-ARC-002 | Duplicated business logic | medium | high |
| AUD-ARC-003 | Unnecessary abstraction | low | medium |

## Database

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-DB-001 | Relationship definition inconsistent with schema | medium | medium |
| AUD-DB-002 | Suspicious or destructive migration | high | medium |
| AUD-DB-003 | Nullable/non-nullable mismatch | low | medium |

## Testing

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-TST-001 | Critical business flow without test coverage | medium | low |
| AUD-TST-002 | Tests that do not verify meaningful behavior | low | medium |
| AUD-TST-003 | Missing authorization tests | medium | low |

## Laravel conventions

| ID | Name | Severity | Confidence |
| --- | --- | --- | --- |
| AUD-CON-001 | Version-inappropriate API usage | medium | high |
| AUD-CON-002 | Reinventing framework functionality | medium | high |
| AUD-CON-003 | Misuse of framework lifecycle or features | low | medium |
