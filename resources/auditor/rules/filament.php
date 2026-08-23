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
    [
        'id' => 'AUD-FIL-003',
        'name' => 'Filament table or form performs unbounded or repeated database work',
        'domain' => 'performance',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'A Filament panel loads data it does not need: Select/checkbox options hydrating an entire table without search limiting, relationship columns or state closures querying per record, counts computed by loading collections instead of `withCount`, or option lists rebuilt from the database on every request without caching.',
        'why_it_matters' => 'Filament tables render per-record cells for every visible row, so a query inside `getStateUsing`/`formatStateUsing` is a hidden N+1, and unbounded select options transfer whole tables to serve one dropdown.',
        'recommendation' => 'Make selects searchable with an options limit instead of preloading everything; eager load relationship columns via the table query; replace per-row collection loads with `withCount()`/aggregate columns; cache stable option lists. Verify custom cells actually run per row before reporting.',
        'evidence' => [
            'The resource/table/form definition and the expensive call within it.',
            'For per-row closures: proof they execute per rendered record.',
            'The table size source that makes the option list or cell queries grow.',
        ],
        'false_positive_considerations' => [
            'Small lookup tables (statuses, categories) are fine to preload into selects.',
            'Closures that only format already-loaded attributes add no queries.',
            'A panel restricted to tiny internal datasets may not justify restructuring; keep severity honest.',
        ],
        'references' => [
            'https://filamentphp.com/docs/tables/columns/getting-started',
            'https://filamentphp.com/docs/forms/fields/select',
        ],
        'applicability' => [
            'packages' => ['filament/filament'],
        ],
    ],
];
