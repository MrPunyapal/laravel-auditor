<?php

declare(strict_types=1);

use LaravelAuditor\Context\Migrations\MigrationSourceInspector;

it('detects dangerous operations inside up but ignores down and comments', function () {
    $php = <<<'PHP'
<?php
// Schema::drop('comment_only');
return new class {
    public function up(): void
    {
        Schema::dropIfExists('old_invoices');
        Schema::rename('accounts', 'customers');
        Schema::table('users', function (Blueprint $builder): void {
            $builder->dropColumn('legacy_id');
            $builder->string('name', 20)->nullable()->change();
            $builder->unique('email');
        });
        DB::statement('ALTER TABLE users DROP COLUMN secret');
    }
    public function down(): void {
        Schema::drop('down_only');
    }
};
PHP;
    $result = (new MigrationSourceInspector)->inspect($php);
    $codes = array_column($result['indicators'], 'code');

    expect($result['up_found'])->toBeTrue()
        ->and($result['coverage'])->toBe('recognized_php_source_patterns')
        ->and($codes)->toContain('source_table_drop', 'source_table_rename', 'source_blueprint_dropcolumn', 'source_column_change', 'source_constraint_or_index', 'source_raw_sql')
        ->and(json_encode($result))->not->toContain('down_only', 'secret', 'comment_only');
});

it('does not include migration source or arbitrary SQL text in its output', function () {
    $php = <<<'PHP'
<?php
return new class {
    public function up() {
        DB::unprepared("DELETE FROM customers WHERE token = 'private-secret'");
    }
};
PHP;
    $result = (new MigrationSourceInspector)->inspect($php);

    expect($result['indicators'][0]['code'])->toBe('source_raw_sql')
        ->and(json_encode($result))->not->toContain('private-secret', 'DELETE FROM');
});

it('does not claim safety when up is absent', function () {
    $result = (new MigrationSourceInspector)->inspect('<?php return new class { public function down() { Schema::drop("users"); } };');

    expect($result['up_found'])->toBeFalse()
        ->and($result['indicators'])->toBe([])
        ->and($result['reason'])->toContain('No named up() method');
});

it('fails closed on invalid PHP instead of silently returning no risks', function () {
    $result = (new MigrationSourceInspector)->inspect('<?php function up( {');

    expect($result['up_found'])->toBeFalse()
        ->and($result['reason'])->toContain('not valid PHP');
});

it('flags unknown blueprint callbacks instead of interpreting them as safe', function () {
    $php = '<?php class M { public function up() { Schema::table("users", $this->callback()); } }';
    $result = (new MigrationSourceInspector)->inspect($php);

    expect(array_column($result['indicators'], 'code'))->toBe(['unresolved_schema_callback']);
});

it('does not infer concrete table names from concatenated expressions', function () {
    $php = <<<'PHP'
<?php
return new class {
    public function up() {
        Schema::drop('tenant_' . $this->suffix);
    }
};
PHP;
    $result = (new MigrationSourceInspector)->inspect($php);

    expect($result['indicators'])->toHaveCount(1)
        ->and($result['indicators'][0]['table'])->toBeNull()
        ->and($result['deployment_safety_verified'] ?? false)->toBeFalse();
});
