<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-PEST-001',
        'name' => 'PHPUnit class tests in a Pest-first suite',
        'domain' => 'testing',
        'severity' => 'low',
        'confidence' => 'low',
        'description' => 'The app uses Pest as its test framework but new tests are written as PHPUnit classes without a documented reason, splitting conventions.',
        'why_it_matters' => 'Mixed styles make the suite harder to extend and review.',
        'recommendation' => 'Write new tests in Pest unless the file must be a PHPUnit class (for example a legacy data provider).',
        'evidence' => [
            'tests/Pest.php (or pestphp/pest) and the PHPUnit class test that should be Pest.',
        ],
        'false_positive_considerations' => [
            'A few remaining PHPUnit classes during a migration are expected.',
        ],
        'references' => [
            'https://pestphp.com/docs',
        ],
        'applicability' => [
            'packages' => ['pestphp/pest'],
        ],
    ],
];
