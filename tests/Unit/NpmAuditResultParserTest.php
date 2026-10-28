<?php

declare(strict_types=1);

use LaravelAuditor\Context\Collectors\NpmAuditResultParser;

it('normalizes npm audit vulnerabilities and fix availability', function () {
    $result = NpmAuditResultParser::parse([
        'auditReportVersion' => 2,
        'vulnerabilities' => [
            'example-lib' => [
                'severity' => 'high',
                'range' => '<2.0.0',
                'isDirect' => true,
                'via' => [['name' => 'example-lib', 'url' => 'https://example.test/advisory']],
                'fixAvailable' => ['name' => 'example-lib', 'version' => '2.0.0', 'isSemVerMajor' => true],
            ],
        ],
    ]);

    expect($result)->not->toBeNull()
        ->and($result['available'])->toBeTrue()
        ->and($result['count'])->toBe(1)
        ->and($result['vulnerabilities'][0]['package'])->toBe('example-lib')
        ->and($result['vulnerabilities'][0]['severity'])->toBe('high')
        ->and($result['vulnerabilities'][0]['direct'])->toBeTrue()
        ->and($result['vulnerabilities'][0]['fix_available']['isSemVerMajor'])->toBeTrue();
});

it('rejects npm error JSON and malformed vulnerability data', function () {
    expect(NpmAuditResultParser::parse(['error' => ['code' => 'EAI_AGAIN'], 'vulnerabilities' => []]))->toBeNull();
    expect(NpmAuditResultParser::parse(['vulnerabilities' => []], 2))->toBeNull();
    expect(NpmAuditResultParser::parse(['vulnerabilities' => []], 1))->toBeNull();
    expect(NpmAuditResultParser::parse(['vulnerabilities' => []], null))->toBeNull();
    expect(NpmAuditResultParser::parse(['vulnerabilities' => ['lib' => []]]))->toBeNull();
    expect(NpmAuditResultParser::parse(['error' => ['code' => 'ENOLOCK']]))->toBeNull();
    expect(NpmAuditResultParser::parse(['vulnerabilities' => 'invalid']))->toBeNull();
    expect(NpmAuditResultParser::parse(['vulnerabilities' => ['lib' => 'invalid']]))->toBeNull();

    expect(NpmAuditResultParser::parse(['vulnerabilities' => []]))->toMatchArray([
        'available' => true,
        'count' => 0,
    ]);
});
