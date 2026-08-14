<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-SEC-001',
        'name' => 'Missing authorization boundary',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'high',
        'description' => 'A resource-modifying or sensitive route, controller action, or model operation is not protected by an authorization check (policy, gate, or middleware).',
        'why_it_matters' => 'Without an authorization boundary, any authenticated user may be able to access or modify resources they should not own or view.',
        'recommendation' => 'Add a policy or gate for the resource, and enforce it in the controller, route middleware, or form request. Do not rely on the UI hiding the link as a security boundary.',
        'evidence' => [
            'The route or controller action that performs the sensitive operation.',
            'The model/policy files that should govern access.',
            'How similar resources are authorized elsewhere in the app.',
        ],
        'false_positive_considerations' => [
            'The action may be intentionally public and does not involve a per-resource owner.',
            'Authorization may be enforced upstream via route middleware that is not visible in the controller.',
        ],
        'references' => [
            'https://laravel.com/docs/authorization',
            'https://laravel.com/docs/12.x/policies',
        ],
        'applicability' => [],
        'metadata' => [
            'audit_focus' => 'Check every mutating/sensitive route for an explicit authorization check.',
        ],
    ],
    [
        'id' => 'AUD-SEC-002',
        'name' => 'Mass assignment risk',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'medium',
        'description' => 'An Eloquent model is fully unguarded or has an over-permissive `$fillable` and user input is passed directly to a mass-assignment call.',
        'why_it_matters' => 'Attackers may be able to set attributes they should not control (roles, balances, ownership flags) by submitting unexpected fields.',
        'recommendation' => 'Define an explicit `$fillable` allow-list (or use `$guarded`) and pass only validated fields to `create`, `fill`, or `updateOrCreate`. Avoid `request()->all()` in mass-assignment calls.',
        'evidence' => [
            'The model definition showing `$guarded = []` or `$fillable` list.',
            'The controller/service code that mass-assigns request input.',
        ],
        'false_positive_considerations' => [
            'The fields passed may already be whitelisted by validation or explicit array construction.',
            'An intentionally fully-writable model may be acceptable when all attributes are safe.',
        ],
        'references' => [
            'https://laravel.com/docs/eloquent#mass-assignment',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-SEC-003',
        'name' => 'Sensitive data exposure',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'medium',
        'description' => 'Sensitive values (credentials, tokens, personal data) are exposed through responses, logs, exceptions, or environment output.',
        'why_it_matters' => 'Exposing secrets or personal data can lead to credential theft, account takeover, or regulatory/compliance failures.',
        'recommendation' => 'Hide secrets from logs and error responses, redact personal data in API responses, and never echo environment variables containing credentials.',
        'evidence' => [
            'The exact location where the value is exposed (response, log call, dump).',
            'The sensitive field or secret name.',
        ],
        'false_positive_considerations' => [
            'The value may be intentionally public or non-sensitive in context.',
            'Logging may already redact the value via a masker.',
        ],
        'references' => [
            'https://laravel.com/docs/errors#the-exception-handler',
            'https://laravel.com/docs/12.x/logging',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-SEC-004',
        'name' => 'Unsafe redirect to user-controlled URL',
        'domain' => 'security',
        'severity' => 'medium',
        'confidence' => 'high',
        'description' => 'A redirect target is taken from user input without an allow-list, enabling open-redirect attacks.',
        'why_it_matters' => 'Open redirects can be used for phishing and can break the trust boundary of the application domain.',
        'recommendation' => 'Redirect to a validated allow-list destination (e.g. `intended()` with a fallback, or an explicit route) instead of echoing an arbitrary user-supplied URL.',
        'evidence' => [
            'The redirect call and where its target originates.',
        ],
        'false_positive_considerations' => [
            'The target may be validated against an allow-list before use.',
        ],
        'references' => [
            'https://laravel.com/docs/authentication#authenticating-users',
            'https://owasp.org/www-community/attacks/Open_redirect',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-SEC-005',
        'name' => 'Dangerous file handling',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'medium',
        'description' => 'User-controlled input is used to read, write, move, or delete files without a validated allow-list, or uploaded files are stored/executed unsafely.',
        'why_it_matters' => 'Unvalidated file paths and uploads can leak source files, overwrite application files, or execute attacker-controlled content.',
        'recommendation' => 'Validate uploads with Laravel\'s file rules, store them outside the public web root or on a disk with a generated name, and never concatenate user input into filesystem paths.',
        'evidence' => [
            'The file operation and the user-controlled input that reaches it.',
            'Whether the destination is public or uses a generated filename.',
        ],
        'false_positive_considerations' => [
            'The path may already be constrained to a known directory and filename.',
            'Storage::putFile() with a generated name is typically safe.',
        ],
        'references' => [
            'https://laravel.com/docs/filesystem',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-SEC-006',
        'name' => 'Secrets committed or hardcoded',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'high',
        'description' => 'Credentials, API keys, private tokens, or other secrets appear in source control, committed .env files, or hardcoded in application code.',
        'why_it_matters' => 'Committed secrets are difficult to revoke and can be extracted from git history even after they are removed from the working tree.',
        'recommendation' => 'Move secrets to environment variables or a secret manager, rotate any exposed credentials, and ensure `.env` is gitignored.',
        'evidence' => [
            'The file and the secret-looking value or committed environment file.',
        ],
        'false_positive_considerations' => [
            'Example or placeholder values in .env.example are expected.',
            'Public client identifiers are not automatically secrets.',
        ],
        'references' => [
            'https://laravel.com/docs/configuration#environment-configuration',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-SEC-007',
        'name' => 'Risky debug or error exposure',
        'domain' => 'security',
        'severity' => 'medium',
        'confidence' => 'high',
        'description' => 'Debug mode, verbose exception rendering, or leftover dump statements can expose stack traces, environment values, or application internals in a non-local environment.',
        'why_it_matters' => 'Debug output leaks implementation details and sometimes secrets, which helps an attacker map the application.',
        'recommendation' => 'Keep `app.debug` false outside local development, remove leftover `dd`/`dump` calls, and avoid rendering raw exception details to end users.',
        'evidence' => [
            'The configuration key or dump call and the environment it applies to.',
        ],
        'false_positive_considerations' => [
            'Debug mode is expected in local development.',
            'A dump used only in tests is not a production exposure.',
        ],
        'references' => [
            'https://laravel.com/docs/errors',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-SEC-008',
        'name' => 'Unsafe validation assumptions',
        'domain' => 'security',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'A mutating or sensitive endpoint trusts client-side checks, missing server-side validation, or treats `required` as authorization or type safety.',
        'why_it_matters' => 'Unvalidated input is a common source of mass-assignment, injection, and business-rule bypasses.',
        'recommendation' => 'Validate every untrusted input on the server with a Form Request or `Validator`, including types, ownership ids, and authorization-relevant fields.',
        'evidence' => [
            'The controller/action and the request input it consumes.',
            'The missing or incomplete validation rules.',
        ],
        'false_positive_considerations' => [
            'Internal console commands or trusted signed URLs may not need the same validation.',
        ],
        'references' => [
            'https://laravel.com/docs/validation',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-SEC-009',
        'name' => 'Unsafe configuration usage',
        'domain' => 'security',
        'severity' => 'medium',
        'confidence' => 'high',
        'description' => 'Security-sensitive configuration (debug, session, cookie, CORS, trusted proxies, or hashed secrets) is set unsafely for a non-local environment.',
        'why_it_matters' => 'Unsafe configuration can expose internals, weaken session protections, or make CSRF/CORS checks ineffective.',
        'recommendation' => 'Use environment-specific config: `app.debug` false outside local, secure session cookies in production, and explicit trusted-proxy/CORS allow-lists.',
        'evidence' => [
            'The configuration key and its effective value.',
            'The environment where the value applies.',
        ],
        'false_positive_considerations' => [
            'Local development values are expected to be looser.',
        ],
        'references' => [
            'https://laravel.com/docs/configuration',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-SEC-010',
        'name' => 'Insecure authentication pattern',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'medium',
        'description' => 'Authentication stores secrets in plaintext, skips rate limiting on login, uses a homemade auth loop, or disables important session protections without a documented reason.',
        'why_it_matters' => 'Weak authentication is a direct path to account takeover.',
        'recommendation' => 'Use Laravel\'s auth scaffolding, hash passwords, rate-limit login, and keep session/cookie security settings at framework defaults unless there is a proven need.',
        'evidence' => [
            'The authentication code or config that is unsafe.',
        ],
        'false_positive_considerations' => [
            'Custom auth may be required for SSO or an external identity provider.',
        ],
        'references' => [
            'https://laravel.com/docs/authentication',
        ],
        'applicability' => [],
    ],
];
