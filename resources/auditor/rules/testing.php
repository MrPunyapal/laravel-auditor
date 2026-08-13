<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-TST-001',
        'name' => 'Critical business flow without test coverage',
        'domain' => 'testing',
        'severity' => 'medium',
        'confidence' => 'low',
        'description' => 'A critical business flow (purchase, authorization, data mutation, integration) has no tests that exercise its behavior.',
        'why_it_matters' => 'Critical flows without tests can regress silently, and regressions in them are the most expensive to find.',
        'recommendation' => 'Add feature/unit tests that exercise the happy path, key edge cases, and failure modes of the critical flow.',
        'evidence' => [
            'The critical flow (route/controller/service).',
            'The test directory showing no test covers it.',
        ],
        'false_positive_considerations' => [
            'Coverage may exist under a different name or location.',
            'Do not equate line coverage with test quality; require meaningful behavior tests.',
        ],
        'references' => [
            'https://laravel.com/docs/11.x/testing',
            'https://pestphp.com/docs',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-TST-002',
        'name' => 'Tests that do not verify meaningful behavior',
        'domain' => 'testing',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'A test asserts implementation details, mocks everything away, or checks only that code runs without verifying behavior.',
        'why_it_matters' => 'Tests that pin implementation details or assert nothing meaningful give false confidence and break on refactoring.',
        'recommendation' => 'Rewrite the test to assert observable behavior and outcomes rather than internals.',
        'evidence' => [
            'The test file and what it actually asserts.',
            'The behavior it should verify.',
        ],
        'false_positive_considerations' => [
            'Some implementation detail assertions are acceptable where they protect a real invariant.',
        ],
        'references' => [
            'https://pestphp.com/docs',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-TST-003',
        'name' => 'Missing authorization tests',
        'domain' => 'testing',
        'severity' => 'medium',
        'confidence' => 'low',
        'description' => 'Authorization boundaries (policies, gates, route middleware) have no tests proving unauthorized users are rejected.',
        'why_it_matters' => 'Authorization is a common source of security regressions; without tests, accidental loosening goes unnoticed.',
        'recommendation' => 'Add tests asserting that unauthorized users receive the expected rejection (403/redirect) for each sensitive route/action.',
        'evidence' => [
            'The authorization boundary (policy/gate/middleware).',
            'The test suite showing no rejection test exists.',
        ],
        'false_positive_considerations' => [
            'Tests may exist in a shared base test class or data-driven suite.',
        ],
        'references' => [
            'https://laravel.com/docs/authorization',
        ],
        'applicability' => [],
    ],
];
