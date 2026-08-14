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
    [
        'id' => 'AUD-PER-004',
        'name' => 'Repeated query without reuse',
        'domain' => 'performance',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'The same query or expensive lookup is executed multiple times in one request instead of being reused, memoized, or eager-loaded.',
        'why_it_matters' => 'Repeated queries add latency that grows with request complexity and traffic.',
        'recommendation' => 'Reuse the first result, eager-load the relationship, or memoize the lookup for the request lifetime.',
        'evidence' => [
            'Two or more call sites executing the same query in one request path.',
        ],
        'false_positive_considerations' => [
            'The second query may be intentionally different or already cached.',
        ],
        'references' => [
            'https://laravel.com/docs/eloquent',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-PER-005',
        'name' => 'Synchronous work that belongs on a queue',
        'domain' => 'performance',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'Email, notifications, remote HTTP, PDF/image processing, or other non-essential I/O runs synchronously in a web request when a queued job is the idiomatic Laravel fit.',
        'why_it_matters' => 'Synchronous side effects make requests slow and fragile when the remote service is down.',
        'recommendation' => 'Dispatch a queued job or notification for work that is not required to produce the HTTP response.',
        'evidence' => [
            'The synchronous call and the request path that contains it.',
        ],
        'false_positive_considerations' => [
            'The user may be waiting on the result, so the work cannot be deferred.',
        ],
        'references' => [
            'https://laravel.com/docs/queues',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-PER-006',
        'name' => 'Inefficient collection or database usage',
        'domain' => 'performance',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'A large dataset is loaded into a collection and filtered/sorted in PHP, or a query omits pagination/`chunk`/`cursor` where the table can grow.',
        'why_it_matters' => 'Loading unbounded rows wastes memory and time as the table grows.',
        'recommendation' => 'Push filters, sorts, and limits into the query, and paginate or chunk large result sets.',
        'evidence' => [
            'The query/collection site and why the dataset can be large.',
        ],
        'false_positive_considerations' => [
            'A known tiny lookup table does not need pagination.',
        ],
        'references' => [
            'https://laravel.com/docs/pagination',
            'https://laravel.com/docs/eloquent#chunking-results',
        ],
        'applicability' => [],
    ],
];
