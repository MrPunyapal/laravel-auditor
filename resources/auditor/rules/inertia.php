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
    [
        'id' => 'AUD-IN-003',
        'name' => 'Inertia shared props recomputed or oversized on every response',
        'domain' => 'performance',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'HandleInertiaRequests::share() computes expensive values (counts, menus, permission maps, dashboards) eagerly on every Inertia response — including responses whose pages never use them — or shares payloads large enough to dominate response size.',
        'why_it_matters' => 'Shared props are evaluated for every Inertia render of every page. Expensive eager shares turn into a fixed tax on all traffic; oversized shares inflate every payload.',
        'recommendation' => 'Mark props the current page may not need as partial-reload-only (`Inertia::optional()`; `Inertia::lazy()` on Inertia v1/v2), compose heavy data per page instead of globally, and keep shared data to a small explicit subset. Verify pages actually use each eagerly-shared value before recommending changes.',
        'evidence' => [
            'The share() callback and the work it performs per response.',
            'Which pages consume each shared prop (or proof they do not).',
            'The size driver for oversized shared values.',
        ],
        'false_positive_considerations' => [
            'Small stable values (auth user subset, flash messages) are the intended use.',
            'Partial-reload props (`Inertia::optional()`) only resolve when requested; an expensive optional prop is not automatically a problem.',
            'Partial reloads may legitimately rely on shared props being present.',
        ],
        'references' => [
            'https://inertiajs.com/shared-data',
            'https://inertiajs.com/partial-reloads',
        ],
        'applicability' => [
            'packages' => ['inertiajs/inertia-laravel'],
        ],
    ],
];
