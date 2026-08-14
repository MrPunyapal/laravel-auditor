<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-FIL-001',
        'name' => 'Filament resource missing policy',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'medium',
        'description' => 'A Filament resource allows view/create/update/delete without a corresponding policy (or canAccess/can* methods).',
        'why_it_matters' => 'Filament CRUD is a privileged UI. Missing policies let any authenticated panel user mutate records.',
        'recommendation' => 'Add a model policy and wire it to the resource, or implement canView/canEdit/canDelete explicitly.',
        'evidence' => [
            'The Filament resource and the missing policy/can* method.',
        ],
        'false_positive_considerations' => [
            'A panel that is already restricted to a single admin role may not need per-resource policies.',
        ],
        'references' => [
            'https://filamentphp.com/docs/panels/resources/getting-started#authorization',
        ],
        'applicability' => [
            'packages' => ['filament/filament'],
        ],
    ],
    [
        'id' => 'AUD-FIL-002',
        'name' => 'Unrestricted Filament bulk action',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'medium',
        'description' => 'A destructive Filament bulk action (delete, forceDelete, restore, custom) has no authorization or confirmation.',
        'why_it_matters' => 'Bulk actions multiply a missing check across every selected row.',
        'recommendation' => 'Authorize the bulk action and require confirmation for destructive operations.',
        'evidence' => [
            'The bulk action definition and the missing authorize/requiresConfirmation call.',
        ],
        'false_positive_considerations' => [
            'A bulk action that only exports or tags records may not be destructive.',
        ],
        'references' => [
            'https://filamentphp.com/docs/tables/actions#bulk-actions',
        ],
        'applicability' => [
            'packages' => ['filament/filament'],
        ],
    ],
];
