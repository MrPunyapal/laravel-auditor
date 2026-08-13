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
];
