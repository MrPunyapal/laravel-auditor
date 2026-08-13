<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-PER-001',
        'name' => 'N+1 query risk',
        'domain' => 'performance',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'A loop or collection rendering triggers repeated queries for related models without eager loading.',
        'why_it_matters' => 'N+1 queries multiply database round-trips with dataset size and are a common cause of slow pages under real load.',
        'recommendation' => 'Eager load the relationships (e.g. `with("author")`) or use `load` when relationships are needed after querying.',
        'evidence' => [
            'The loop/query site and the relationship being fetched per iteration.',
            'A count of queries or the presence of `with` usage as contrast.',
        ],
        'false_positive_considerations' => [
            'The loop may run over a small fixed set (e.g. a handful of rows).',
            'The relationship may already be cached or loaded by a preceding `with`.',
            'Do not report every loop; require evidence of an actual repeated query.',
        ],
        'references' => [
            'https://laravel.com/docs/eloquent-relationships#eager-loading',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-PER-002',
        'name' => 'Expensive work in the request lifecycle',
        'domain' => 'performance',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'Expensive, repeatable, or time-consuming work is performed synchronously in the request lifecycle where a queue, cache, or deferred computation is a better fit.',
        'why_it_matters' => 'Synchronous heavy work increases response latency and reduces throughput; repeated expensive computation wastes resources.',
        'recommendation' => 'Move non-essential work to a queued job, memoize/cache expensive computation, or defer it until needed.',
        'evidence' => [
            'The expensive call and its location in the request path.',
            'Why the work is expensive (external API, large computation, I/O).',
        ],
        'false_positive_considerations' => [
            'The work may be necessary for the response and cannot be deferred.',
            'The work may already be cached.',
            'Do not recommend caching without a strong justification.',
        ],
        'references' => [
            'https://laravel.com/docs/queues',
            'https://laravel.com/docs/cache',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-PER-003',
        'name' => 'Missing index on a frequently-queried column',
        'domain' => 'performance',
        'severity' => 'low',
        'confidence' => 'low',
        'description' => 'A column that is frequently filtered, joined, or ordered by lacks a database index.',
        'why_it_matters' => 'Unindexed lookups degrade to table scans and slow down as the table grows.',
        'recommendation' => 'Add a migration that indexes the column (or composite index) where query patterns justify it.',
        'evidence' => [
            'The column and the queries that filter/join on it.',
            'Schema/migration showing the column lacks an index.',
        ],
        'false_positive_considerations' => [
            'Small tables may not need an index.',
            'The column may already be covered by a composite index.',
            'Require evidence of actual query patterns; do not index every column.',
        ],
        'references' => [
            'https://laravel.com/docs/migrations#creating-indexes',
        ],
        'applicability' => [],
    ],
];
