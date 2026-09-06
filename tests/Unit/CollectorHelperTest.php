<?php

declare(strict_types=1);

use LaravelAuditor\Context\Collectors\DatabaseSchemaCollector;
use LaravelAuditor\Context\Collectors\DependenciesCollector;

it('scopes schema collection away from an empty listing', function () {
    expect(DatabaseSchemaCollector::hasSchemaScope(null))->toBeTrue();
    expect(DatabaseSchemaCollector::hasSchemaScope(['app']))->toBeTrue();
    expect(DatabaseSchemaCollector::hasSchemaScope([]))->toBeFalse();
});

it('explains composer audit failures with the exit code and stderr', function () {
    expect(DependenciesCollector::composerAuditFailureReason(
        100,
        '',
        "curl error 7 while downloading https://packagist.org/api/security-advisories/\nFailed to download",
    ))->toBe('composer audit failed (exit 100): curl error 7 while downloading https://packagist.org/api/security-advisories/ Failed to download');

    expect(DependenciesCollector::composerAuditFailureReason(2, '{"broken":', ''))
        ->toBe('composer audit produced no parseable JSON output (exit 2): {"broken":');

    expect(DependenciesCollector::composerAuditFailureReason(null, '', ''))
        ->toBe('composer audit produced no parseable JSON output (exit -1)');

    $long = str_repeat('x', 400);
    $reason = DependenciesCollector::composerAuditFailureReason(1, '', $long);

    expect($reason)->toStartWith('composer audit failed (exit 1): ')
        ->and(strlen($reason))->toBeLessThan(400)
        ->and($reason)->toEndWith('...');
});
