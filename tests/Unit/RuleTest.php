<?php

declare(strict_types=1);

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Audit\Enums\AuditDomain;
use LaravelAuditor\Audit\Enums\Confidence;
use LaravelAuditor\Audit\Enums\Severity;
use LaravelAuditor\Audit\Rules\RuleDefinition;
use LaravelAuditor\Audit\Rules\RuleRegistry;

it('creates a rule definition with the full schema', function () {
    $rule = new RuleDefinition(
        id: 'AUD-SEC-001',
        name: 'Missing authorization boundary',
        domain: AuditDomain::Security,
        severity: Severity::High,
        confidence: Confidence::High,
        description: 'Checks for missing authorization.',
        whyItMatters: 'Security boundary.',
        recommendation: 'Add policies.',
        evidence: ['Controller method', 'Policy existence'],
        falsePositiveConsiderations: ['Public endpoints'],
        references: ['https://laravel.com/docs/authorization'],
    );

    expect($rule->id)->toBe('AUD-SEC-001');
    expect($rule->domain)->toBe(AuditDomain::Security);
    expect($rule->evidence)->toBe(['Controller method', 'Policy existence']);
    expect($rule->references)->toHaveCount(1);
});

it('hydrates a rule definition from an array', function () {
    $rule = RuleDefinition::fromArray([
        'id' => 'AUD-PER-001',
        'name' => 'N+1 query risk',
        'domain' => 'performance',
        'severity' => 'medium',
        'confidence' => 'high',
        'description' => 'N+1.',
        'why_it_matters' => 'Slow.',
        'recommendation' => 'Eager load.',
        'evidence' => ['Query site'],
        'references' => [],
    ]);

    expect($rule->id)->toBe('AUD-PER-001');
    expect($rule->domain)->toBe(AuditDomain::Performance);
    expect($rule->severity)->toBe(Severity::Medium);
    expect($rule->confidence)->toBe(Confidence::High);
});

it('discovers and validates the built-in rules', function () {
    $registry = new RuleRegistry(
        new Filesystem,
        [__DIR__.'/../../resources/auditor/rules'],
    );

    expect($registry->count())->toBeGreaterThanOrEqual(50);
    expect($registry->find('AUD-SEC-001'))->not->toBeNull();
    expect($registry->all())->toHaveCount($registry->count());
});

it('returns rules for a given domain', function () {
    $registry = new RuleRegistry(
        new Filesystem,
        [__DIR__.'/../../resources/auditor/rules'],
    );

    $security = $registry->forDomain(AuditDomain::Security);

    expect($security)->not->toBeEmpty();
    expect(array_unique(array_map(fn ($rule): string => $rule->domain->value, $security)))->toBe(['security']);
});

it('counts rules by domain', function () {
    $registry = new RuleRegistry(
        new Filesystem,
        [__DIR__.'/../../resources/auditor/rules'],
    );

    $counts = $registry->countsByDomain();

    expect($counts)->toHaveKeys(['security', 'performance', 'architecture', 'database', 'testing', 'conventions']);
    expect(array_sum($counts))->toBe($registry->count());
});

it('evaluates version and package applicability', function () {
    $rule = RuleDefinition::fromArray([
        'id' => 'AUD-X-100',
        'name' => 'Livewire only',
        'domain' => 'conventions',
        'severity' => 'low',
        'confidence' => 'low',
        'description' => 'Applies to Livewire apps on Laravel 11+.',
        'applicability' => [
            'laravel_min' => '11.0.0',
            'laravel_max' => '13.0.0',
            'packages' => ['livewire/livewire'],
        ],
    ]);

    expect($rule->applies('12.0.0', ['livewire/livewire' => 'v4.0.0']))->toBeTrue();
    expect($rule->applies('10.0.0', ['livewire/livewire']))->toBeFalse();
    expect($rule->applies('14.0.0', ['livewire/livewire']))->toBeFalse();
    expect($rule->applies('12.0.0', []))->toBeFalse();
    expect($rule->applies(null, ['livewire/livewire']))->toBeTrue();
});

it('filters the registry by applicability', function () {
    $registry = new RuleRegistry(
        new Filesystem,
        [__DIR__.'/../../resources/auditor/rules'],
    );

    expect($registry->applicable('12.0.0', []))->not->toBeEmpty();
    expect($registry->find('AUD-CON-001')?->applies('12.0.0'))->toBeTrue();
    expect($registry->find('AUD-CON-001')?->applies('7.0.0'))->toBeFalse();
});

it('rejects a rule file that does not return an array', function () {
    $dir = sys_get_temp_dir().'/laravel-auditor-test-'.uniqid();
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/broken.php', '<?php return "nope";');

    $registry = new RuleRegistry(new Filesystem, [$dir]);

    expect(fn () => $registry->all())->toThrow(RuntimeException::class, 'must return an array');

    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);
});

it('rejects a rule file that contains a non-array item', function () {
    $dir = sys_get_temp_dir().'/laravel-auditor-test-'.uniqid();
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/broken.php', '<?php return ["nope"];');

    $registry = new RuleRegistry(new Filesystem, [$dir]);

    expect(fn () => $registry->all())->toThrow(RuntimeException::class, 'must contain only rule arrays');

    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);
});

it('rejects a rule missing a required key', function () {
    $dir = sys_get_temp_dir().'/laravel-auditor-test-'.uniqid();
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/broken.php', '<?php return [["id" => "AUD-X-001", "name" => "No domain", "severity" => "low", "description" => "missing domain"]];');

    $registry = new RuleRegistry(new Filesystem, [$dir]);

    expect(fn () => $registry->all())->toThrow(RuntimeException::class);

    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);
});

it('rejects duplicate rule IDs across files', function () {
    $dir = sys_get_temp_dir().'/laravel-auditor-test-'.uniqid();
    mkdir($dir, 0777, true);
    file_put_contents($dir.'/a.php', '<?php return [["id" => "AUD-X-001", "name" => "A", "domain" => "security", "severity" => "low", "confidence" => "low", "description" => "d"]];');
    file_put_contents($dir.'/b.php', '<?php return [["id" => "AUD-X-001", "name" => "B", "domain" => "security", "severity" => "low", "confidence" => "low", "description" => "d"]];');

    $registry = new RuleRegistry(new Filesystem, [$dir]);

    expect(fn () => $registry->all())->toThrow(RuntimeException::class, 'Duplicate audit rule ID');

    array_map('unlink', glob($dir.'/*') ?: []);
    rmdir($dir);
});
