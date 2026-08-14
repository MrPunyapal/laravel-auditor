<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-QUE-001',
        'name' => 'Job missing retry or timeout bounds',
        'domain' => 'conventions',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'A queued job that performs external I/O has no $tries/$timeout (or middleware) and can retry forever or hang a worker.',
        'why_it_matters' => 'Unbounded jobs exhaust workers and can hammer a failing third-party API.',
        'recommendation' => 'Set $tries, $timeout, and backoff on jobs that leave the process boundary.',
        'evidence' => [
            'The job class and the missing retry/timeout configuration.',
        ],
        'false_positive_considerations' => [
            'Trivial in-process jobs may not need custom retry settings.',
        ],
        'references' => [
            'https://laravel.com/docs/queues#max-job-attempts-and-timeout',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-QUE-002',
        'name' => 'Sensitive model serialized on the queue',
        'domain' => 'security',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'A job constructor accepts an Eloquent model that includes hidden secrets, or SerializesModels will reload it without considering that the payload is stored in Redis/database.',
        'why_it_matters' => 'Queue payloads are often readable by anyone with cache/DB access.',
        'recommendation' => 'Pass IDs, not full models with secrets. Avoid putting tokens or raw passwords on the job.',
        'evidence' => [
            'The job constructor and the sensitive value or model it stores.',
        ],
        'false_positive_considerations' => [
            'SerializesModels storing an ID is the normal, safe Laravel pattern.',
        ],
        'references' => [
            'https://laravel.com/docs/queues#handling-failed-jobs',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-QUE-003',
        'name' => 'Sync queue driver in a non-local environment',
        'domain' => 'performance',
        'severity' => 'medium',
        'confidence' => 'high',
        'description' => 'QUEUE_CONNECTION=sync (or queue.default=sync) is used outside local/testing, so notified/mailed/queued work runs in the request.',
        'why_it_matters' => 'The sync driver defeats queues and makes every job a latency and failure problem on the web request.',
        'recommendation' => 'Use redis/database/sqs in staging and production. Keep sync for local/tests only.',
        'evidence' => [
            'The effective queue.default value and the environment.',
        ],
        'false_positive_considerations' => [
            'Local and automated tests are expected to use sync.',
        ],
        'references' => [
            'https://laravel.com/docs/queues#driver-prerequisites',
        ],
        'applicability' => [],
    ],
];
