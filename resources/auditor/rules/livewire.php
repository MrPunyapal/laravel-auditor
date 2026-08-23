<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-LW-001',
        'name' => 'Livewire action missing authorization',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'high',
        'description' => 'A Livewire action mutates or exposes a resource without an authorize/policy check.',
        'why_it_matters' => 'Livewire actions are invokable from the client. Missing authorization is equivalent to an unprotected controller action.',
        'recommendation' => 'Call $this->authorize() (or a policy) in every mutating Livewire action. Do not treat wire:click visibility as a security boundary.',
        'evidence' => [
            'The Livewire component method and the missing authorization call.',
            'The model/policy that should govern the action.',
        ],
        'false_positive_considerations' => [
            'Read-only computed properties and public-by-design forms may not need a policy.',
        ],
        'references' => [
            'https://livewire.laravel.com/docs/security',
        ],
        'applicability' => [
            'packages' => ['livewire/livewire'],
        ],
    ],
    [
        'id' => 'AUD-LW-002',
        'name' => 'Unvalidated Livewire public property',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'medium',
        'description' => 'A public Livewire property is client-writable without #[Locked], locked(), or validation rules, and it maps to a sensitive attribute.',
        'why_it_matters' => 'Public properties are mass-assignable from the browser unless locked or validated.',
        'recommendation' => 'Lock IDs and ownership fields, and validate every writable public property.',
        'evidence' => [
            'The component property and how the client can set it.',
        ],
        'false_positive_considerations' => [
            'Search boxes and other intentionally public fields are fine.',
        ],
        'references' => [
            'https://livewire.laravel.com/docs/security',
        ],
        'applicability' => [
            'packages' => ['livewire/livewire'],
        ],
    ],
    [
        'id' => 'AUD-LW-003',
        'name' => 'Livewire render/update path re-queries or carries oversized state',
        'domain' => 'performance',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'A Livewire component re-runs queries on every render/update cycle (expensive render() methods, computed properties whose queries repeat on each subsequent update), or holds large public properties (full collections/model graphs) that serialize into every request snapshot and response.',
        'why_it_matters' => 'Livewire re-renders components on nearly every interaction, so per-render queries multiply with user activity, and large public properties inflate both network payloads and server-side hydration cost on every update. Computed properties are memoized within a single request but run again on the next update, so per-update costs recur.',
        'recommendation' => 'Eager load what render() iterates; keep public properties minimal (identifiers plus paginated slices) instead of whole datasets; move heavy derived state to computed properties so it is not serialized, and consider `#[Computed(persist: true)]`/cache only when freshness and invalidation requirements allow it. Verify the property is not intentionally shared before shrinking it.',
        'evidence' => [
            'The component render()/computed method and the queries executed there.',
            'The update frequency driver (wire:model inputs, polling, events).',
            'For oversized state: the public property and what the client actually consumes from it.',
        ],
        'false_positive_considerations' => [
            'Components rendering small fixed datasets may not justify restructuring.',
            'Computed properties already memoized for the current request are fine; only per-update repetition is a cost.',
            'A public property used as the actual editing surface must stay writable; suggest narrowing only what the UI does not need.',
        ],
        'references' => [
            'https://livewire.laravel.com/docs/computed-properties',
            'https://livewire.laravel.com/docs/properties',
        ],
        'applicability' => [
            'packages' => ['livewire/livewire'],
        ],
    ],
];
