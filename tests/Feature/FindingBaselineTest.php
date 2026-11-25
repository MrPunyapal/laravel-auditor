<?php

declare(strict_types=1);

function baselineTestFinding(string $id, string $severity = 'high', string $title = 'Missing authorization'): array
{
    return [
        'id' => $id,
        'rule_id' => 'AUD-SEC-001',
        'title' => $title,
        'domain' => 'security',
        'severity' => $severity,
        'confidence' => 'confirmed',
        'status' => 'open',
        'summary' => 'A route accepts untrusted input.',
        'why_it_matters' => 'The action is not authorized.',
        'evidence' => [
            ['type' => 'file', 'reference' => 'app/Http/Controllers/PostController.php', 'line' => 42],
        ],
        'affected_resources' => ['app/Http/Controllers/PostController.php'],
        'symbol' => 'App\\Http\\Controllers\\PostController@destroy',
    ];
}

function baselineTestJsonFile(array $payload): string
{
    $path = sys_get_temp_dir().'/laravel-auditor-baseline-'.uniqid('', true).'.json';
    file_put_contents($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

    return $path;
}

it('writes a deterministic minimal baseline sorted by stable finding id', function () {
    $findingsPath = baselineTestJsonFile([
        baselineTestFinding('F-2', 'low', 'Second finding'),
        baselineTestFinding('F-1', 'high', 'First finding'),
    ]);
    $baselinePath = sys_get_temp_dir().'/laravel-auditor-baseline-output-'.uniqid('', true).'.json';
    $secondBaselinePath = sys_get_temp_dir().'/laravel-auditor-baseline-output-'.uniqid('', true).'.json';

    $this->artisan('auditor:baseline', ['--findings' => $findingsPath, '--output' => $baselinePath])
        ->assertSuccessful();
    $first = file_get_contents($baselinePath);

    $this->artisan('auditor:baseline', ['--findings' => $findingsPath, '--output' => $secondBaselinePath])
        ->assertSuccessful();

    expect(json_decode($first, true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray([
            'format' => 'laravel-auditor.findings-baseline',
            'version' => 1,
        ])
        ->and(array_column(json_decode($first, true, flags: JSON_THROW_ON_ERROR)['findings'], 'id'))
        ->toBe(['F-1', 'F-2'])
        ->and(json_decode($first, true, flags: JSON_THROW_ON_ERROR)['findings'][0])
        ->not->toHaveKey('evidence')
        ->and(file_get_contents($secondBaselinePath))->toBe($first);

    unlink($findingsPath);
    unlink($baselinePath);
    unlink($secondBaselinePath);
});

it('rejects empty or duplicate finding ids when creating a baseline', function () {
    foreach ([
        [baselineTestFinding('')],
        [baselineTestFinding('F-1'), baselineTestFinding('F-1')],
    ] as $findings) {
        $findingsPath = baselineTestJsonFile($findings);
        $baselinePath = sys_get_temp_dir().'/laravel-auditor-baseline-output-'.uniqid('', true).'.json';

        $this->artisan('auditor:baseline', ['--findings' => $findingsPath, '--output' => $baselinePath])
            ->expectsOutputToContain('unique, non-empty finding ids')
            ->assertFailed();

        expect(file_exists($baselinePath))->toBeFalse();
        unlink($findingsPath);
    }
});

it('never overwrites its findings input while creating a baseline', function () {
    $findingsPath = baselineTestJsonFile([baselineTestFinding('F-1')]);
    $contents = file_get_contents($findingsPath);

    $this->artisan('auditor:baseline', ['--findings' => $findingsPath, '--output' => $findingsPath])
        ->expectsOutputToContain('must not resolve to the input findings file')
        ->assertFailed();

    expect(file_get_contents($findingsPath))->toBe($contents);
    unlink($findingsPath);
});

it('reports new persistent and absent baseline findings without claiming verified fixes', function () {
    $findingsPath = baselineTestJsonFile([
        baselineTestFinding('F-1', 'high', 'Persistent finding'),
        baselineTestFinding('F-2', 'low', 'New finding'),
    ]);
    $baselinePath = baselineTestJsonFile([
        'format' => 'laravel-auditor.findings-baseline',
        'version' => 1,
        'findings' => [
            ['id' => 'F-1', 'rule_id' => 'AUD-SEC-001', 'title' => 'Old title', 'domain' => 'security', 'severity' => 'high'],
            ['id' => 'F-3', 'rule_id' => 'AUD-DB-002', 'title' => 'No longer present', 'domain' => 'database', 'severity' => 'medium'],
        ],
    ]);

    $exit = Artisan::call('auditor:report', [
        '--format' => 'json',
        '--findings' => $findingsPath,
        '--baseline' => $baselinePath,
    ]);
    $payload = json_decode(Artisan::output(), true);

    expect($exit)->toBe(0)
        ->and($payload['baseline']['summary'])->toBe(['new' => 1, 'persistent' => 1, 'absent' => 1])
        ->and(array_column($payload['baseline']['new'], 'id'))->toBe(['F-2'])
        ->and(array_column($payload['baseline']['persistent'], 'id'))->toBe(['F-1'])
        ->and(array_column($payload['baseline']['absent'], 'id'))->toBe(['F-3'])
        ->and($payload['baseline']['notes'][0])->toContain('does not prove the finding was fixed');

    $markdownExit = Artisan::call('auditor:report', [
        '--findings' => $findingsPath,
        '--baseline' => $baselinePath,
    ]);
    $markdown = Artisan::output();

    expect($markdownExit)->toBe(0)
        ->and($markdown)->toContain('## Baseline comparison')
        ->and($markdown)->toContain('F-2')
        ->and($markdown)->toContain('F-3');

    unlink($findingsPath);
    unlink($baselinePath);
});

it('fails CI only for new open findings at or above the threshold when a baseline is supplied', function () {
    $findingsPath = baselineTestJsonFile([
        baselineTestFinding('F-1', 'high', 'Persistent finding'),
        baselineTestFinding('F-2', 'low', 'New low finding'),
    ]);
    $baselinePath = baselineTestJsonFile([
        'format' => 'laravel-auditor.findings-baseline',
        'version' => 1,
        'findings' => [
            ['id' => 'F-1', 'rule_id' => 'AUD-SEC-001', 'title' => 'Persistent finding', 'domain' => 'security', 'severity' => 'high'],
        ],
    ]);

    $this->artisan('auditor:ci', [
        '--findings' => $findingsPath,
        '--baseline' => $baselinePath,
        '--fail-on' => 'high',
        '--format' => 'json',
    ])->assertSuccessful();

    file_put_contents($findingsPath, json_encode([
        baselineTestFinding('F-1', 'high', 'Persistent finding'),
        baselineTestFinding('F-2', 'high', 'New high finding'),
    ], JSON_THROW_ON_ERROR));

    $this->artisan('auditor:ci', [
        '--findings' => $findingsPath,
        '--baseline' => $baselinePath,
        '--fail-on' => 'high',
        '--format' => 'json',
    ])->expectsOutputToContain('1 new open finding')
        ->assertFailed();

    unlink($findingsPath);
    unlink($baselinePath);
});

it('marks new findings in SARIF and GitHub annotations when a baseline is supplied', function () {
    $findingsPath = baselineTestJsonFile([baselineTestFinding('F-2', 'high', 'New finding')]);
    $baselinePath = baselineTestJsonFile([
        'format' => 'laravel-auditor.findings-baseline',
        'version' => 1,
        'findings' => [],
    ]);

    Artisan::call('auditor:report', [
        '--findings' => $findingsPath,
        '--baseline' => $baselinePath,
        '--format' => 'sarif',
    ]);
    $sarif = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($sarif['runs'][0]['results'][0]['baselineState'])->toBe('new');

    Artisan::call('auditor:report', [
        '--findings' => $findingsPath,
        '--baseline' => $baselinePath,
        '--format' => 'text',
    ]);

    expect(Artisan::output())->toContain('[HIGH] [NEW] New finding');

    Artisan::call('auditor:ci', [
        '--findings' => $findingsPath,
        '--baseline' => $baselinePath,
        '--format' => 'github',
    ]);
    $annotations = Artisan::output();

    expect($annotations)->toContain('::notice title=Laravel Auditor baseline::1 new')
        ->and($annotations)->toContain('[NEW] AUD-SEC-001');

    unlink($findingsPath);
    unlink($baselinePath);
});

it('rejects malformed or unsupported baselines instead of reporting a clean comparison', function () {
    $findingsPath = baselineTestJsonFile([baselineTestFinding('F-1')]);
    $badBaselines = [
        'not json',
        json_encode(['format' => 'laravel-auditor.findings-baseline', 'version' => 99, 'findings' => []], JSON_THROW_ON_ERROR),
        json_encode([
            'format' => 'laravel-auditor.findings-baseline',
            'version' => 1,
            'findings' => [
                ['id' => 'F-1', 'rule_id' => 'AUD-SEC-001', 'title' => 'First', 'domain' => 'security', 'severity' => 'high'],
                ['id' => 'F-1', 'rule_id' => 'AUD-SEC-001', 'title' => 'Duplicate', 'domain' => 'security', 'severity' => 'high'],
            ],
        ], JSON_THROW_ON_ERROR),
        json_encode([
            'format' => 'laravel-auditor.findings-baseline',
            'version' => 1,
            'findings' => [],
            'evidence' => ['must not be copied to baseline files'],
        ], JSON_THROW_ON_ERROR),
    ];

    foreach ($badBaselines as $contents) {
        $baselinePath = sys_get_temp_dir().'/laravel-auditor-baseline-invalid-'.uniqid('', true).'.json';
        file_put_contents($baselinePath, $contents);

        $this->artisan('auditor:ci', [
            '--findings' => $findingsPath,
            '--baseline' => $baselinePath,
            '--format' => 'json',
        ])->assertFailed();

        unlink($baselinePath);
    }

    unlink($findingsPath);
});

it('rejects baseline comparisons combined with partial change scopes', function () {
    $findingsPath = baselineTestJsonFile([]);
    $baselinePath = baselineTestJsonFile([
        'format' => 'laravel-auditor.findings-baseline',
        'version' => 1,
        'findings' => [],
    ]);

    $this->artisan('auditor:ci', [
        '--findings' => $findingsPath,
        '--baseline' => $baselinePath,
        '--dirty' => true,
    ])->expectsOutputToContain('cannot be combined with --base or --dirty')
        ->assertFailed();

    unlink($findingsPath);
    unlink($baselinePath);
});

it('retains status only in opt-in version 2 baselines and detects regressions without duplicate candidates', function () {
    $old = [
        baselineTestFinding('raised', 'low'),
        [...baselineTestFinding('reopened', 'high'), 'status' => 'fixed'],
        [...baselineTestFinding('both', 'medium'), 'status' => 'accepted'],
        baselineTestFinding('unchanged', 'high'),
        baselineTestFinding('lowered', 'critical'),
        baselineTestFinding('closed', 'low'),
    ];
    $current = [
        baselineTestFinding('raised', 'critical'),
        baselineTestFinding('reopened', 'high'),
        baselineTestFinding('both', 'high'),
        baselineTestFinding('unchanged', 'high'),
        baselineTestFinding('lowered', 'high'),
        [...baselineTestFinding('closed', 'high'), 'status' => 'fixed'],
    ];
    $oldPath = baselineTestJsonFile($old);
    $currentPath = baselineTestJsonFile($current);
    $baselinePath = baselineTestJsonFile([]);

    try {
        $this->artisan('auditor:baseline', [
            '--findings' => $oldPath,
            '--output' => $baselinePath,
            '--with-status' => true,
        ])->assertSuccessful();

        $saved = json_decode((string) file_get_contents($baselinePath), true, flags: JSON_THROW_ON_ERROR);
        expect($saved['version'])->toBe(2)
            ->and($saved['findings'][0]['status'])->toBe('accepted')
            ->and($saved['findings'][0])->not->toHaveKey('evidence');

        $exit = Artisan::call('auditor:report', [
            '--findings' => $currentPath,
            '--baseline' => $baselinePath,
            '--format' => 'json',
        ]);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        expect($exit)->toBe(0)
            ->and($report['baseline']['status_history_available'])->toBeTrue()
            ->and(array_column($report['baseline']['regressions'], 'id'))->toBe(['both', 'closed', 'raised', 'reopened'])
            ->and($report['baseline']['regressions'][0]['severity_increased'])->toBeTrue()
            ->and($report['baseline']['regressions'][0]['reopened'])->toBeTrue();

        $options = ['--findings' => $currentPath, '--baseline' => $baselinePath, '--fail-on' => 'high'];
        $this->artisan('auditor:ci', $options)->assertSuccessful();
        $this->artisan('auditor:ci', [...$options, '--fail-on-regression' => true])
            ->expectsOutputToContain('3 new or regressed open finding(s)')
            ->assertFailed();
    } finally {
        unlink($oldPath);
        unlink($currentPath);
        unlink($baselinePath);
    }
});

it('requires known historical status for the stricter policy without rejecting legacy baselines by default', function () {
    $findingsPath = baselineTestJsonFile([baselineTestFinding('F-1', 'high')]);
    $baselinePath = baselineTestJsonFile([
        'format' => 'laravel-auditor.findings-baseline',
        'version' => 1,
        'findings' => [
            ['id' => 'F-1', 'rule_id' => 'AUD-SEC-001', 'title' => 'Missing authorization', 'domain' => 'security', 'severity' => 'low'],
        ],
    ]);

    try {
        $options = ['--findings' => $findingsPath, '--baseline' => $baselinePath];
        $this->artisan('auditor:ci', $options)->assertSuccessful();
        $this->artisan('auditor:ci', [...$options, '--fail-on-regression' => true])
            ->expectsOutputToContain('historical status is unknown')
            ->assertFailed();
        $this->artisan('auditor:ci', ['--findings' => $findingsPath, '--fail-on-regression' => true])
            ->expectsOutputToContain('requires a version 2 --baseline')
            ->assertFailed();

        expect(Artisan::call('auditor:report', [...$options, '--format' => 'json']))->toBe(0);
        $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        expect($report['baseline']['status_history_available'])->toBeFalse()
            ->and($report['baseline']['regressions'][0]['severity_increased'])->toBeTrue()
            ->and($report['baseline']['regressions'][0]['previous_status'])->toBeNull()
            ->and($report['baseline']['regressions'][0]['reopened'])->toBeFalse();
    } finally {
        unlink($findingsPath);
        unlink($baselinePath);
    }
});

it('rejects missing or invalid status in a version 2 baseline', function () {
    $findingsPath = baselineTestJsonFile([baselineTestFinding('F-1')]);
    $entry = ['id' => 'F-1', 'rule_id' => 'AUD-SEC-001', 'title' => 'Missing authorization', 'domain' => 'security', 'severity' => 'high'];

    try {
        foreach ([$entry, [...$entry, 'status' => 'unknown']] as $invalid) {
            $baselinePath = baselineTestJsonFile([
                'format' => 'laravel-auditor.findings-baseline',
                'version' => 2,
                'findings' => [$invalid],
            ]);

            try {
                $this->artisan('auditor:ci', ['--findings' => $findingsPath, '--baseline' => $baselinePath])
                    ->expectsOutputToContain('invalid or missing status')
                    ->assertFailed();
            } finally {
                unlink($baselinePath);
            }
        }
    } finally {
        unlink($findingsPath);
    }
});

it('distinguishes empty findings lists from JSON objects in the regression gate', function () {
    $findingsPath = baselineTestJsonFile([]);
    $baselinePath = baselineTestJsonFile([
        'format' => 'laravel-auditor.findings-baseline',
        'version' => 2,
        'findings' => [],
    ]);
    $options = ['--findings' => $findingsPath, '--baseline' => $baselinePath, '--fail-on-regression' => true];

    try {
        foreach (['{}', '{"findings":{}}', '{"findings":{"0":{}}}'] as $malformed) {
            file_put_contents($findingsPath, $malformed);
            $this->artisan('auditor:ci', $options)->assertFailed();
        }

        file_put_contents($findingsPath, '{"findings":[]}');
        $this->artisan('auditor:ci', $options)->assertSuccessful();

        file_put_contents($baselinePath, '{"format":"laravel-auditor.findings-baseline","version":2,"findings":{}}');
        $this->artisan('auditor:ci', $options)->assertFailed();
    } finally {
        unlink($findingsPath);
        unlink($baselinePath);
    }
});
