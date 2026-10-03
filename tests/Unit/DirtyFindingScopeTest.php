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

/**
 * Builds a finding with a single evidence entry of the given type.
 */
function typedFinding(string $id, string $type, string $reference): Finding
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
        evidence: new EvidenceCollection(new Evidence($type, $reference)),
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

it('keeps a finding with no file reference when nothing changed', function () {
    $collection = new FindingCollection(
        scopedFinding('F-unscoped'),
        scopedFinding('F-file', ['app/Models/User.php']),
    );

    $filtered = $collection->touching([]);

    expect($filtered)->toHaveCount(1);
    expect($filtered[0]->id)->toBe('F-unscoped');
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

it('trusts a file-typed reference even without a recognizable extension', function () {
    // An agent that cites `app/Services/UserService` as file evidence still gets scoped.
    $collection = new FindingCollection(typedFinding('F-1', 'file', 'app/Services/UserService'));

    expect($collection->touching(['app/Services/UserService']))->toHaveCount(1);
});

it('never matches a route, config, or symbol reference', function () {
    $collection = new FindingCollection(
        typedFinding('F-1', 'route', 'GET api/users.index'),
        typedFinding('F-2', 'config', 'services.stripe.secret'),
        typedFinding('F-3', 'symbol', 'App\\Models\\User@save'),
    );

    // Even when a changed path happens to equal the reference text.
    expect($collection->touching(['GET api/users.index', 'services.stripe.secret'])->isEmpty())->toBeTrue();
});

it('matches migration and test typed references', function () {
    $collection = new FindingCollection(
        typedFinding('F-1', 'migration', 'database/migrations/2026_01_01_000000_add_body_to_posts'),
        typedFinding('F-2', 'test', 'tests/Feature/PostTest'),
    );

    expect($collection->touching([
        'database/migrations/2026_01_01_000000_add_body_to_posts',
        'tests/Feature/PostTest',
    ]))->toHaveCount(2);
});

it('resolves an absolute file reference against the application base', function () {
    $collection = new FindingCollection(typedFinding('F-1', 'file', base_path('app/Models/User.php')));

    expect($collection->touching(['app/Models/User.php']))->toHaveCount(1);
});

it('rejects a file reference that escapes the application base', function () {
    $collection = new FindingCollection(
        typedFinding('F-1', 'file', '../sibling/File.php'),
        typedFinding('F-2', 'file', '/etc/passwd'),
    );

    expect($collection->touching(['../sibling/File.php', '/etc/passwd'])->isEmpty())->toBeTrue();
});

it('keeps a dotfile reference intact instead of trimming it into a name', function () {
    $collection = new FindingCollection(typedFinding('F-1', 'file', '.env.example'));

    expect($collection->touching(['.env.example']))->toHaveCount(1);
});

it('falls back to the shape test for an unknown evidence type', function () {
    $collection = new FindingCollection(
        typedFinding('F-1', 'custom', 'app/Models/User.php'),
        typedFinding('F-2', 'custom', 'not a path at all'),
    );

    // An unrecognized type must not silently stop matching.
    expect($collection->touching(['app/Models/User.php']))->toHaveCount(1);
});

it('matches a dotfile named in affected resources', function () {
    $collection = new FindingCollection(scopedFinding('F-1', [], ['.env.example']));

    expect($collection->touching(['.env.example']))->toHaveCount(1);
});
