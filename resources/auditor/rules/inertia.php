<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-IN-001',
        'name' => 'Inertia shared data leaks sensitive attributes',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'medium',
        'description' => 'Inertia::share() (or HandleInertiaRequests) exposes passwords, tokens, or other hidden attributes to the client.',
        'why_it_matters' => 'Shared Inertia props are visible in the page payload. Secrets there are public to every visitor of that page.',
        'recommendation' => 'Share only an explicit user subset (id, name, email) and never the full Authenticatable model.',
        'evidence' => [
            'The share() call and the attributes it includes.',
        ],
        'false_positive_considerations' => [
            'Sharing a custom UserResource/array of public fields is expected.',
        ],
        'references' => [
            'https://inertiajs.com/shared-data',
        ],
        'applicability' => [
            'packages' => ['inertiajs/inertia-laravel'],
        ],
    ],
    [
        'id' => 'AUD-IN-002',
        'name' => 'Inertia endpoint missing authorization',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'high',
        'description' => 'A controller that returns Inertia::render() for a sensitive page does not authorize the user.',
        'why_it_matters' => 'Inertia pages are still server-rendered responses. Missing authorize() is the same bug as a missing web policy.',
        'recommendation' => 'Authorize the action before Inertia::render(), just as you would for a Blade view.',
        'evidence' => [
            'The controller action and the missing authorization check.',
        ],
        'false_positive_considerations' => [
            'Marketing or other public Inertia pages do not need a policy.',
        ],
        'references' => [
            'https://inertiajs.com/who-is-it-for',
        ],
        'applicability' => [
            'packages' => ['inertiajs/inertia-laravel'],
        ],
    ],
];
