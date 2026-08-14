<?php

declare(strict_types=1);

use LaravelAuditor\Context\ContextCollector;
use LaravelAuditor\Context\ContextRegistry;
use LaravelAuditor\MCP\McpServer;
use LaravelAuditor\MCP\McpTool;
use LaravelAuditor\MCP\McpToolRegistry;

it('registers an MCP tool for every context collector', function () {
    $registry = app(McpToolRegistry::class);
    $tools = $registry->all();

    expect($tools)->toHaveCount(11);
    expect(array_map(static fn (McpTool $tool): string => $tool->name, $tools))->toEqualCanonicalizing(app(ContextRegistry::class)->names());
    expect($tools[0]->toJson())->toHaveKeys(['name', 'description', 'inputSchema']);
});

it('serves initialize, ping, tools/list, and tools/call over stdio', function () {
    $input = fopen('php://temp', 'r+');
    $output = fopen('php://temp', 'r+');

    $messages = [
        ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2024-11-05']],
        ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping'],
        ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list'],
        ['jsonrpc' => '2.0', 'id' => 4, 'method' => 'tools/call', 'params' => ['name' => 'project_info']],
        ['jsonrpc' => '2.0', 'id' => 5, 'method' => 'tools/call', 'params' => ['name' => 'nope']],
        ['jsonrpc' => '2.0', 'id' => 6, 'method' => 'unknown/method'],
        ['jsonrpc' => '1.0', 'id' => 7, 'method' => 'ping'],
        'not-json',
        '',
    ];

    foreach ($messages as $message) {
        fwrite($input, (is_string($message) ? $message : json_encode($message, JSON_THROW_ON_ERROR))."\n");
    }

    rewind($input);

    $exit = (new McpServer(app(McpToolRegistry::class), $input, $output))->run();

    expect($exit)->toBe(0);

    rewind($output);

    $responses = array_values(array_filter(array_map(
        static fn (string $line): ?array => json_decode($line, true),
        explode("\n", (string) stream_get_contents($output)),
    )));

    expect($responses[0]['result']['serverInfo']['name'])->toBe('laravel-auditor');
    expect($responses[1]['result'])->toBe([]);
    expect($responses[2]['result']['tools'])->toHaveCount(11);
    expect($responses[3]['result']['content'][0]['text'])->toContain('laravel_version');
    expect($responses[4]['error']['code'])->toBe(-32602);
    expect($responses[5]['error']['code'])->toBe(-32601);
    expect($responses[6]['error']['code'])->toBe(-32600);
});

it('returns a structured error when a tool throws', function () {
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

    $tool = new McpTool('broken', 'Broken collector', $collector);

    $result = $tool->call();

    expect($result['isError'])->toBeTrue();
    expect($result['content'][0]['text'])->toContain('boom');
});
