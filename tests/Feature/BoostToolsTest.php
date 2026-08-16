<?php

declare(strict_types=1);

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\MCP\Boost\BoostMcpRegistrar;
use LaravelAuditor\MCP\Boost\Tools\AuditTool;
use LaravelAuditor\MCP\Boost\Tools\ProjectInfoTool;
use LaravelAuditor\Support\BoostDetector;

it('exposes one Boost tool adapter for every context collector', function () {
    $tools = app(BoostMcpRegistrar::class)->toolClasses();

    expect($tools)->toHaveCount(11);
    expect($tools)->each->toBeString();

    foreach ($tools as $toolClass) {
        expect(is_subclass_of($toolClass, AuditTool::class))->toBeTrue();
        expect(is_subclass_of($toolClass, Tool::class))->toBeTrue();
    }
});

it('registers a Boost tool for each collector that matches its metadata', function () {
    $tools = app(BoostMcpRegistrar::class)->toolClasses();

    foreach ($tools as $toolClass) {
        /** @var AuditTool $tool */
        $tool = app($toolClass);

        expect($tool)->toBeInstanceOf(Tool::class);
        expect($tool->name())->not->toBeEmpty();
        expect($tool->title())->not->toBeEmpty();
        expect($tool->description())->not->toBeEmpty();
        expect($tool->annotations())->toHaveKey('readOnlyHint');
    }
});

it('returns collector context as a JSON response when handled', function () {
    /** @var AuditTool $tool */
    $tool = app(ProjectInfoTool::class);

    $response = $tool->handle(new Request);

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->isError())->toBeFalse();
    expect((string) $response->content())->toContain('laravel_version');
});

it('returns an error response when a collector throws', function () {
    $collector = new class implements ContextCollector
    {
        public function name(): string
        {
            return 'broken';
        }

        public function description(): string
        {
            return 'Broken collector';
        }

        public function collect(): array
        {
            throw new RuntimeException('boom');
        }
    };

    $tool = new class($collector) extends AuditTool {};

    $response = $tool->handle(new Request);

    expect($response->isError())->toBeTrue();
    expect((string) $response->content())->toContain('boom');
});

it('merges boost tool classes into boost.mcp.tools.include when Boost is installed', function () {
    $detector = new class extends BoostDetector
    {
        public function isInstalled(): bool
        {
            return true;
        }
    };

    config(['boost.mcp.tools.include' => ['Some\\ExistingTool']]);

    (new BoostMcpRegistrar($detector))->register();

    $include = config('boost.mcp.tools.include');

    expect($include)->toContain('Some\\ExistingTool');
    expect($include)->toHaveCount(12);
    expect($include)->toContain(ProjectInfoTool::class);
});

it('does not touch boost configuration when Boost is absent', function () {
    $detector = new class extends BoostDetector
    {
        public function isInstalled(): bool
        {
            return false;
        }
    };

    config(['boost.mcp.tools.include' => ['Some\\ExistingTool']]);

    (new BoostMcpRegistrar($detector))->register();

    expect(config('boost.mcp.tools.include'))->toBe(['Some\\ExistingTool']);
});
