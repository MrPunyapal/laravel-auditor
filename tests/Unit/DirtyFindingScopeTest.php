<?php

declare(strict_types=1);

use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Evidence\Evidence;
use LaravelAuditor\Audit\Evidence\EvidenceCollection;
use LaravelAuditor\Audit\Findings\Finding;
use LaravelAuditor\Audit\Findings\FindingCollection;

/**
 * Builds a finding with the given evidence references and affected resources.
 *
 * @param  list<string>  $evidence
 * @param  list<string>  $resources
 */
function scopedFinding(string $id, array $evidence = [], array $resources = []): Finding
{
    return new Finding(
        id: $id,
        ruleId: 'AUD-X',
        title: 'Test',
        domain: AuditDomain::Security,
        severity: Severity::High,
        confidence: Confidence::High,
        summary: 'Summary',
        whyItMatters: 'Why',
        evidence: new EvidenceCollection(...array_map(
            static fn (string $reference): Evidence => Evidence::file($reference),
            $evidence,
        )),
        affectedResources: $resources,
    );
}

it('keeps only findings that touch a changed file', function () {
    $collection = new FindingCollection(
        scopedFinding('F-1', ['app/Models/User.php']),
        scopedFinding('F-2', ['app/Jobs/SendMail.php']),
    );

    $filtered = $collection->touching(['app/Models/User.php']);

    expect($filtered)->toHaveCount(1);
    expect($filtered[0]->id)->toBe('F-1');
});

it('matches on affected resources when no evidence points at a file', function () {
    $collection = new FindingCollection(
        scopedFinding('F-1', [], ['app/Http/Controllers/PostController.php']),
        scopedFinding('F-2', [], ['app/Http/Controllers/CommentController.php']),
    );

    expect($collection->touching(['app/Http/Controllers/PostController.php'])[0]->id)->toBe('F-1');
});

it('returns nothing when no file changed', function () {
    $collection = new FindingCollection(scopedFinding('F-1', ['app/Models/User.php']));

    expect($collection->touching([])->isEmpty())->toBeTrue();
});

it('keeps a finding that references no file at all', function () {
    // A finding with no file reference cannot be proven unrelated to the change.
    $collection = new FindingCollection(
        scopedFinding('F-1'),
        scopedFinding('F-2', ['app/Models/User.php']),
    );

    $filtered = $collection->touching(['app/Jobs/SendMail.php']);

    expect($filtered)->toHaveCount(1);
    expect($filtered[0]->id)->toBe('F-1');
});

it('does not match routes, config keys, or symbols that contain dots', function () {
    $collection = new FindingCollection(
        scopedFinding('F-1', ['GET api/users.index']),
        scopedFinding('F-2', ['services.stripe.secret']),
        scopedFinding('F-3', ['App\\Models\\User@save']),
    );

    expect($collection->touching(['app/Models/User.php'])->isEmpty())->toBeTrue();
});

it('normalizes windows separators and a leading ./', function () {
    $collection = new FindingCollection(
        scopedFinding('F-1', ['app\\Models\\User.php']),
        scopedFinding('F-2', ['./app/Jobs/SendMail.php']),
    );

    $filtered = $collection->touching(['app/Models/User.php', 'app/Jobs/SendMail.php']);

    expect($filtered)->toHaveCount(2);
});

it('matches blade views and migrations, not directories', function () {
    $collection = new FindingCollection(
        scopedFinding('F-1', ['resources/views/posts/index.blade.php']),
        scopedFinding('F-2', ['database/migrations/2026_01_01_000000_create_posts_table.php']),
        scopedFinding('F-3', ['app/Models']),
    );

    $filtered = $collection->touching([
        'resources/views/posts/index.blade.php',
        'database/migrations/2026_01_01_000000_create_posts_table.php',
    ]);

    expect($filtered)->toHaveCount(2);
});
