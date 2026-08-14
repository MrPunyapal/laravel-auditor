<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-CON-001',
        'name' => 'Version-inappropriate API usage',
        'domain' => 'conventions',
        'severity' => 'medium',
        'confidence' => 'high',
        'description' => 'The code uses a framework API that is deprecated, removed, or not idiomatic for the installed Laravel version.',
        'why_it_matters' => 'Using outdated or removed APIs causes maintenance debt, warnings, and breakage on upgrades.',
        'recommendation' => 'Use the version-appropriate idiomatic Laravel API for the installed framework version.',
        'evidence' => [
            'The installed Laravel version.',
            'The code using the outdated API and the correct alternative.',
        ],
        'false_positive_considerations' => [
            'The application may intentionally target a specific framework version for legacy compatibility.',
            'Only report when the installed version is known.',
        ],
        'references' => [
            'https://laravel.com/docs/upgrade',
        ],
        'applicability' => [
            'laravel_min' => '8.0.0',
        ],
    ],
    [
        'id' => 'AUD-CON-002',
        'name' => 'Reinventing framework functionality',
        'domain' => 'conventions',
        'severity' => 'medium',
        'confidence' => 'high',
        'description' => 'Custom code duplicates a standard Laravel mechanism (validation, events, queues, cache, routing, notifications, etc.) that would be clearer and more maintainable.',
        'why_it_matters' => 'Reinventing framework features increases maintenance burden and risks subtle behavioral differences from the framework standard.',
        'recommendation' => 'Replace the custom implementation with the standard Laravel mechanism when the framework provides a clear equivalent.',
        'evidence' => [
            'The custom implementation and the Laravel mechanism it replaces.',
        ],
        'false_positive_considerations' => [
            'Custom code may exist for good reasons the framework mechanism cannot serve.',
            'Only recommend replacement when the standard mechanism is clearly better.',
        ],
        'references' => [
            'https://laravel.com/docs/architecture-concepts',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-CON-003',
        'name' => 'Misuse of framework lifecycle or features',
        'domain' => 'conventions',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'Framework lifecycle features (service providers, middleware, observers, mutators) are used in ways that cause surprising or incorrect behavior.',
        'why_it_matters' => 'Misused lifecycle features produce subtle, hard-to-debug behavior and violate framework expectations.',
        'recommendation' => 'Use the framework feature in its intended way, matching the documented lifecycle and conventions.',
        'evidence' => [
            'The offending code and how the framework feature should behave.',
        ],
        'false_positive_considerations' => [
            'The usage may be intentional and correct for the application context.',
        ],
        'references' => [
            'https://laravel.com/docs/providers',
            'https://laravel.com/docs/middleware',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-CON-004',
        'name' => 'Framework anti-pattern',
        'domain' => 'conventions',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'Code fights the framework: manual SQL for a simple Eloquent query, a custom dispatcher that duplicates events, or bypassing the container/config/session APIs.',
        'why_it_matters' => 'Fighting the framework creates code that is harder to upgrade and easier to get subtly wrong.',
        'recommendation' => 'Use the idiomatic Laravel API unless there is a measured reason not to.',
        'evidence' => [
            'The custom code and the Laravel API it replaces.',
        ],
        'false_positive_considerations' => [
            'A custom path may exist because the framework API cannot express the need.',
        ],
        'references' => [
            'https://laravel.com/docs',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-CON-005',
        'name' => 'Incorrect framework assumption',
        'domain' => 'conventions',
        'severity' => 'medium',
        'confidence' => 'high',
        'description' => 'Code assumes a config key, middleware alias, guard, or route exists when the installed application does not define it, or assumes a removed helper still exists.',
        'why_it_matters' => 'Incorrect assumptions fail only on some environments or after a seemingly unrelated change.',
        'recommendation' => 'Verify the key, alias, or helper against the installed Laravel version and the application\'s actual config/routes.',
        'evidence' => [
            'The assumed key/alias/helper and the project fact that contradicts it.',
        ],
        'false_positive_considerations' => [
            'The assumption may be satisfied by a package the collector did not see.',
        ],
        'references' => [],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-CON-006',
        'name' => 'Validation or form-request convention gap',
        'domain' => 'conventions',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'A controller with substantial validation or authorization logic does not use a Form Request (or equivalent) when that is clearly the local Laravel convention.',
        'why_it_matters' => 'Inline validation mixed with HTTP glue makes controllers harder to reuse and test.',
        'recommendation' => 'Move validation and authorize() into a Form Request when the action is more than a few rules.',
        'evidence' => [
            'The controller action and the missing Form Request.',
        ],
        'false_positive_considerations' => [
            'Tiny validation arrays in a one-line action are fine.',
        ],
        'references' => [
            'https://laravel.com/docs/validation#form-request-validation',
        ],
        'applicability' => [],
    ],
];
