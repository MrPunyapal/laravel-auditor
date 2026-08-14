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
    [
        'id' => 'AUD-DB-004',
        'name' => 'Missing foreign key',
        'domain' => 'database',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'A column that clearly references another table has no foreign key, allowing orphaned rows and inconsistent deletes.',
        'why_it_matters' => 'Missing foreign keys hide data-integrity bugs until they appear in production.',
        'recommendation' => 'Add a foreign key (and the matching Eloquent relationship) when the column is a real relation, with an explicit onDelete behavior.',
        'evidence' => [
            'The column and the table it should reference.',
            'Schema or migration showing the missing constraint.',
        ],
        'false_positive_considerations' => [
            'Polymorphic relations and some legacy import tables cannot use a simple FK.',
        ],
        'references' => [
            'https://laravel.com/docs/migrations#foreign-key-constraints',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-DB-005',
        'name' => 'Duplicate data modeling',
        'domain' => 'database',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'The same fact is stored in multiple columns or tables that can drift, or a denormalized cache column is treated as a source of truth.',
        'why_it_matters' => 'Duplicated facts diverge and produce inconsistent application behavior.',
        'recommendation' => 'Keep a single source of truth and derive or cache copies explicitly.',
        'evidence' => [
            'The duplicated columns/tables and the code that writes them independently.',
        ],
        'false_positive_considerations' => [
            'Intentional denormalization for performance may be acceptable if one writer owns it.',
        ],
        'references' => [],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-DB-006',
        'name' => 'Inefficient relationship usage',
        'domain' => 'database',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'A relationship is defined or used in a way that forces expensive queries (unconstrained hasMany in a loop, missing pivot indexes, loading huge graphs).',
        'why_it_matters' => 'Bad relationship usage turns simple pages into table scans or N+1 explosions.',
        'recommendation' => 'Constrain the relationship, add the missing index, or load only the columns/relations the response needs.',
        'evidence' => [
            'The relationship method and the query site that makes it expensive.',
        ],
        'false_positive_considerations' => [
            'A relationship used once on a small table is not inherently inefficient.',
        ],
        'references' => [
            'https://laravel.com/docs/eloquent-relationships',
        ],
        'applicability' => [],
    ],
];
