<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-DB-001',
        'name' => 'Relationship definition inconsistent with schema',
        'domain' => 'database',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'An Eloquent relationship points at a foreign key or table that does not match the actual schema.',
        'why_it_matters' => 'Mismatched relationships cause runtime errors, confusing queries, and silent data integrity problems.',
        'recommendation' => 'Align the relationship definition with the schema, or fix the migration so the foreign keys match the model relationships.',
        'evidence' => [
            'The relationship method and the model.',
            'The schema/migration showing the actual columns.',
        ],
        'false_positive_considerations' => [
            'The relationship may rely on a non-conventional but valid key name.',
        ],
        'references' => [
            'https://laravel.com/docs/eloquent-relationships',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-DB-002',
        'name' => 'Suspicious or destructive migration',
        'domain' => 'database',
        'severity' => 'high',
        'confidence' => 'medium',
        'description' => 'A migration performs a destructive or risky operation (dropping a table/column, irreversible data transformation) without safeguards or a down path.',
        'why_it_matters' => 'Destructive migrations can cause data loss or block safe rollbacks in production.',
        'recommendation' => 'Make destructive operations reversible where possible, add data-preserving steps, and verify the migration against production-sized data.',
        'evidence' => [
            'The migration file and the destructive operation.',
            'Why the operation is risky in production.',
        ],
        'false_positive_considerations' => [
            'Intentional destructive migrations for cleanup may be acceptable if reviewed and safe.',
        ],
        'references' => [
            'https://laravel.com/docs/migrations',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-DB-003',
        'name' => 'Nullable/non-nullable mismatch',
        'domain' => 'database',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'A column that should always have a value is nullable, or a column that can be empty is marked non-nullable, creating risk in application code.',
        'why_it_matters' => 'Inconsistent nullability produces hard-to-trace bugs and forces defensive code throughout the app.',
        'recommendation' => 'Align the schema nullability with the actual domain invariants and the code that reads/writes the column.',
        'evidence' => [
            'The schema definition and the application code that assumes the opposite nullability.',
        ],
        'false_positive_considerations' => [
            'Nullability may be intentional for legacy data or partial records.',
        ],
        'references' => [],
        'applicability' => [],
    ],
];
