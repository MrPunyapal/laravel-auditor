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
];
