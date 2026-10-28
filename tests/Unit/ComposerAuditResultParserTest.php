<?php

declare(strict_types=1);

use LaravelAuditor\Context\Collectors\ComposerAuditResultParser;

it('normalizes advisories and preserves other Composer audit categories', function () {
    $payload = ComposerAuditResultParser::parse([
        'advisories' => [
            'acme/package' => [[
                'advisoryId' => 'GHSA-0000-0000-0000',
                'title' => 'Example advisory',
                'affectedVersions' => '<2.0',
                'severity' => 'high',
                'cve' => 'CVE-2026-0001',
                'link' => 'https://example.com',
            ]],
        ],
        'ignored-advisories' => [
            'acme/ignored' => [['advisoryId' => 'GHSA-1111-1111-1111']],
        ],
        'abandoned' => ['acme/old' => 'acme/new'],
        'filter' => ['acme/blocked' => [['listName' => 'malware']]],
        'cooldown' => ['acme/fresh' => ['reason' => 'recently published']],
        'unreachable-repositories' => ['https://repo.example.test'],
    ]);

    expect($payload)->not->toBeNull()
        ->and($payload['available'])->toBeTrue()
        ->and($payload['complete'])->toBeFalse()
        ->and($payload['count'])->toBe(1)
        ->and($payload['advisories'][0]['cve'])->toBe('CVE-2026-0001')
        ->and($payload['ignored_count'])->toBe(1)
        ->and($payload['ignored_advisories'][0]['package'])->toBe('acme/ignored')
        ->and($payload['abandoned_count'])->toBe(1)
        ->and($payload['abandoned']['acme/old'])->toBe('acme/new')
        ->and($payload['filter_count'])->toBe(1)
        ->and($payload['filter']['acme/blocked'][0]['listName'])->toBe('malware')
        ->and($payload['cooldown_count'])->toBe(1)
        ->and($payload['unreachable_repositories'])->toBe(['https://repo.example.test']);
});

it('treats an empty but complete Composer audit as successful', function () {
    expect(ComposerAuditResultParser::parse(['advisories' => []]))->toMatchArray([
        'available' => true,
        'complete' => true,
        'count' => 0,
        'ignored_count' => 0,
        'abandoned_count' => 0,
        'filter_count' => 0,
        'cooldown_count' => 0,
    ]);
});

it('does not treat malformed or error JSON as a clean audit', function () {
    expect(ComposerAuditResultParser::parse([]))->toBeNull();
    expect(ComposerAuditResultParser::parse(['error' => 'network unavailable']))->toBeNull();
    expect(ComposerAuditResultParser::parse(['advisories' => 'invalid']))->toBeNull();
    expect(ComposerAuditResultParser::parse(['advisories' => ['acme/package' => 'invalid']]))->toBeNull();
    expect(ComposerAuditResultParser::parse(['advisories' => [], 'abandoned' => 'invalid']))->toBeNull();
});

it('rejects execution failures with valid JSON', function (?int $exitCode) {
    expect(ComposerAuditResultParser::parse(['advisories' => []], $exitCode))->toBeNull();
})->with([null, -1, 100, 255]);

it('accepts Composer finding statuses', function (int $exitCode) {
    expect(ComposerAuditResultParser::parse(['advisories' => []], $exitCode))->not->toBeNull();
})->with([0, 1, 2, 3]);

it('rejects null optional result categories', function (string $key) {
    expect(ComposerAuditResultParser::parse(['advisories' => [], $key => null]))->toBeNull();
})->with(['ignored-advisories', 'abandoned', 'filter', 'cooldown', 'unreachable-repositories']);
