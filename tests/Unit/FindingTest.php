<?php

declare(strict_types=1);

use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\FindingStatus;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Evidence\Evidence;
use LaravelAuditor\Audit\Evidence\EvidenceCollection;
use LaravelAuditor\Audit\Findings\Finding;
use LaravelAuditor\Audit\Findings\FindingCollection;

it('creates a finding with the full schema', function () {
    $finding = new Finding(
        id: 'F-2026-0001',
        ruleId: 'AUD-SEC-001',
        title: 'Missing authorization boundary',
        domain: AuditDomain::Security,
        severity: Severity::High,
        confidence: Confidence::Confirmed,
        summary: 'Posts can be deleted by any authenticated user.',
        whyItMatters: 'Users could delete content they do not own.',
        evidence: new EvidenceCollection(Evidence::file('app/Http/Controllers/PostController.php', 42)),
        affectedResources: ['app/Http/Controllers/PostController.php'],
        symbol: 'App\Http\Controllers\PostController@destroy',
        recommendation: 'Add a PostPolicy and authorize the deletion.',
    );

    expect($finding->id)->toBe('F-2026-0001');
    expect($finding->ruleId)->toBe('AUD-SEC-001');
    expect($finding->domain)->toBe(AuditDomain::Security);
    expect($finding->severity)->toBe(Severity::High);
    expect($finding->confidence)->toBe(Confidence::Confirmed);
    expect($finding->affectedResources)->toBe(['app/Http/Controllers/PostController.php']);
    expect($finding->status)->toBe(FindingStatus::Open);
});

it('hydrates a finding without a rule_id when no rule matches', function () {
    $finding = Finding::fromArray([
        'id' => 'F-2026-0002',
        'title' => 'Unmapped observation',
        'domain' => 'architecture',
        'severity' => 'low',
        'confidence' => 'confirmed',
        'summary' => 'No rule matches this observation.',
        'why_it_matters' => 'The skill allows findings without a matching rule.',
    ]);

    expect($finding->ruleId)->toBe('');
    expect($finding->toArray())->toHaveKey('rule_id', '');
});

it('applies sensible defaults', function () {
    $finding = new Finding(
        id: 'F-1',
        ruleId: 'AUD-TST-001',
        title: 'No tests',
        domain: AuditDomain::Testing,
        severity: Severity::Medium,
        confidence: Confidence::Medium,
        summary: 'No coverage.',
        whyItMatters: 'Regression risk.',
        evidence: new EvidenceCollection,
    );

    expect($finding->affectedResources)->toBe([]);
    expect($finding->symbol)->toBeNull();
    expect($finding->recommendation)->toBe('');
    expect($finding->status)->toBe(FindingStatus::Open);
    expect($finding->metadata)->toBe([]);
});

it('hydrates a finding from an array', function () {
    $finding = Finding::fromArray([
        'id' => 'F-1',
        'rule_id' => 'AUD-SEC-001',
        'title' => 'Test finding',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'medium',
        'status' => 'open',
        'summary' => 'Summary',
        'why_it_matters' => 'Why it matters',
        'evidence' => [
            ['type' => 'file', 'reference' => 'app/Http/Controllers/PostController.php', 'line' => 42],
        ],
        'affected_resources' => ['app/Http/Controllers/PostController.php'],
        'symbol' => 'App\Http\Controllers\PostController@destroy',
        'recommendation' => 'Fix it.',
    ]);

    expect($finding->ruleId)->toBe('AUD-SEC-001');
    expect($finding->domain)->toBe(AuditDomain::Security);
    expect($finding->severity)->toBe(Severity::High);
    expect($finding->confidence)->toBe(Confidence::Medium);
    expect($finding->evidence)->toHaveCount(1);
    expect($finding->affectedResources)->toBe(['app/Http/Controllers/PostController.php']);
});

it('serializes a finding to an array with snake_case keys', function () {
    $finding = new Finding(
        id: 'F-1',
        ruleId: 'AUD-SEC-001',
        title: 'Test',
        domain: AuditDomain::Security,
        severity: Severity::High,
        confidence: Confidence::High,
        summary: 'Summary',
        whyItMatters: 'Why',
        evidence: new EvidenceCollection,
    );

    $data = $finding->toArray();

    expect($data['rule_id'])->toBe('AUD-SEC-001');
    expect($data['why_it_matters'])->toBe('Why');
    expect($data['affected_resources'])->toBe([]);
    expect($data['evidence'])->toBe([]);
});

it('sorts findings by severity then confidence', function () {
    $collection = new FindingCollection(
        new Finding('F-1', 'AUD-X', 'low', AuditDomain::Conventions, Severity::Low, Confidence::Low, 'a', 'b', new EvidenceCollection),
        new Finding('F-2', 'AUD-X', 'high', AuditDomain::Conventions, Severity::High, Confidence::Medium, 'a', 'b', new EvidenceCollection),
        new Finding('F-3', 'AUD-X', 'critical', AuditDomain::Conventions, Severity::Critical, Confidence::Low, 'a', 'b', new EvidenceCollection),
        new Finding('F-4', 'AUD-X', 'high-confirmed', AuditDomain::Conventions, Severity::High, Confidence::Confirmed, 'a', 'b', new EvidenceCollection),
    );

    $sorted = $collection->sorted()->all();

    expect(array_map(fn (Finding $f): string => $f->id, $sorted))->toBe(['F-3', 'F-4', 'F-2', 'F-1']);
});

it('filters findings by minimum severity', function () {
    $collection = new FindingCollection(
        new Finding('F-1', 'AUD-X', 'low', AuditDomain::Conventions, Severity::Low, Confidence::Low, 'a', 'b', new EvidenceCollection),
        new Finding('F-2', 'AUD-X', 'high', AuditDomain::Conventions, Severity::High, Confidence::Low, 'a', 'b', new EvidenceCollection),
    );

    $filtered = $collection->atLeast(Severity::High)->all();

    expect($filtered)->toHaveCount(1);
    expect($filtered[0]->id)->toBe('F-2');
});

it('counts findings by severity and domain', function () {
    $collection = new FindingCollection(
        new Finding('F-1', 'AUD-X', 'a', AuditDomain::Security, Severity::High, Confidence::Low, 'a', 'b', new EvidenceCollection),
        new Finding('F-2', 'AUD-X', 'b', AuditDomain::Security, Severity::Low, Confidence::Low, 'a', 'b', new EvidenceCollection),
        new Finding('F-3', 'AUD-X', 'c', AuditDomain::Database, Severity::High, Confidence::Low, 'a', 'b', new EvidenceCollection),
    );

    expect($collection->countsBySeverity())->toMatchArray([
        'critical' => 0,
        'high' => 2,
        'medium' => 0,
        'low' => 1,
        'info' => 0,
    ]);

    expect($collection->countsByDomain())->toBe([
        'security' => 2,
        'database' => 1,
    ]);
});

it('supports offset access and iteration', function () {
    $collection = new FindingCollection(
        new Finding('F-1', 'AUD-X', 'a', AuditDomain::Security, Severity::High, Confidence::Low, 'a', 'b', new EvidenceCollection),
    );

    expect(isset($collection[0]))->toBeTrue();
    expect($collection[0]->id)->toBe('F-1');
    expect(iterator_to_array($collection))->toHaveCount(1);

    $collection[] = new Finding('F-2', 'AUD-X', 'b', AuditDomain::Security, Severity::Low, Confidence::Low, 'a', 'b', new EvidenceCollection);
    $collection[1] = new Finding('F-3', 'AUD-X', 'c', AuditDomain::Security, Severity::Low, Confidence::Low, 'a', 'b', new EvidenceCollection);

    expect($collection->jsonSerialize())->toHaveCount(2);
    expect($collection[1]->id)->toBe('F-3');

    unset($collection[1]);

    expect(FindingCollection::fromIterable($collection)->count())->toBe(1);
});
