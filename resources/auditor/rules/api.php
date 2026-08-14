<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-API-001',
        'name' => 'Mutating API route missing token auth',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'high',
        'description' => 'A POST/PUT/PATCH/DELETE API route is not protected by auth:sanctum, auth:api, or equivalent middleware.',
        'why_it_matters' => 'Unauthenticated mutating API routes are a direct data-loss and takeover path.',
        'recommendation' => 'Group mutating API routes behind Sanctum/Passport auth middleware, then authorize the action.',
        'evidence' => [
            'The route, its methods, and the missing auth middleware.',
        ],
        'false_positive_considerations' => [
            'Intentionally public webhooks must verify signatures another way.',
        ],
        'references' => [
            'https://laravel.com/docs/sanctum',
        ],
        'applicability' => [
            'packages' => ['laravel/sanctum'],
        ],
    ],
    [
        'id' => 'AUD-API-002',
        'name' => 'Overly broad API token abilities',
        'domain' => 'security',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'Sanctum/Passport tokens are issued with wildcard abilities (`*` / no abilities check) for clients that only need a narrow action.',
        'why_it_matters' => 'A stolen token then has the full account, not a single scope.',
        'recommendation' => 'Issue the smallest ability set and check tokenCan() on sensitive routes.',
        'evidence' => [
            'The token creation call and the abilities granted.',
        ],
        'false_positive_considerations' => [
            'A first-party SPA using cookie auth is not a token-ability problem.',
        ],
        'references' => [
            'https://laravel.com/docs/sanctum#token-abilities',
        ],
        'applicability' => [
            'packages' => ['laravel/sanctum'],
        ],
    ],
];
