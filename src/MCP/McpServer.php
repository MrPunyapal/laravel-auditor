<?php

declare(strict_types=1);

namespace LaravelAuditor\MCP;

use Throwable;

/**
 * A minimal, spec-compliant Model Context Protocol server over stdio.
 *
 * Implements JSON-RPC 2.0 framing (one JSON object per line on stdin/stdout)
 * with the subset of MCP methods the package needs: initialize, ping,
 * tools/list, and tools/call.
 */
final class McpServer
{
    public const string PROTOCOL_VERSION = '2024-11-05';

    /**
     * @param  resource  $input
     * @param  resource  $output
     */
    public function __construct(
        private readonly McpToolRegistry $tools,
        private mixed $input,
        private mixed $output,
    ) {}

    public function run(): int
    {
        while (($line = fgets($this->input)) !== false) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $message = json_decode($line, true);

            if (! is_array($message)) {
                continue;
            }

            $this->handle($message);
        }

        return 0;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function handle(array $message): void
    {
        $method = (string) ($message['method'] ?? '');
        $id = $message['id'] ?? null;

        if (isset($message['jsonrpc']) && $message['jsonrpc'] !== '2.0') {
            $this->respond($id, null, $this->error(-32600, 'Invalid JSON-RPC version.'));

            return;
        }

        try {
            $result = match ($method) {
                'initialize' => $this->initialize($message),
                'ping' => [],
                'tools/list' => $this->listTools($message),
                'tools/call' => $this->callTool($message),
                default => null,
            };
        } catch (Throwable $e) {
            $this->respond($id, null, $this->error(-32603, 'Internal error: '.$e->getMessage()));

            return;
        }

        if ($result === null) {
            if ($id !== null) {
                $this->respond($id, null, $this->error(-32601, "Method not found: {$method}"));
            }

            return;
        }

        if ($id !== null) {
            $this->respond($id, $result, null);
        }
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function initialize(array $message): array
    {
        $protocolVersion = is_string($message['params']['protocolVersion'] ?? null)
            ? $message['params']['protocolVersion']
            : self::PROTOCOL_VERSION;

        return [
            'protocolVersion' => $protocolVersion,
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
            'serverInfo' => [
                'name' => 'laravel-auditor',
                'version' => '0.1.0',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{tools: list<array<string, mixed>>}
     */
    private function listTools(array $message): array
    {
        return [
            'tools' => array_map(
                static fn (McpTool $tool): array => $tool->toJson(),
                $this->tools->all(),
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>
     */
    private function callTool(array $message): array
    {
        $name = (string) ($message['params']['name'] ?? '');
        $arguments = (array) ($message['params']['arguments'] ?? []);

        foreach ($this->tools->all() as $tool) {
            if ($tool->name === $name) {
                return $tool->call($arguments);
            }
        }

        return $this->error(-32602, "Unknown tool: {$name}");
    }

    /**
     * @param  array<string, mixed>|null  $result
     * @param  array<string, mixed>|null  $error
     */
    private function respond(mixed $id, ?array $result, ?array $error): void
    {
        $response = ['jsonrpc' => '2.0'];

        if ($id !== null) {
            $response['id'] = $id;
        }

        if ($error !== null) {
            $response['error'] = $error;
        } else {
            $response['result'] = $result;
        }

        fwrite($this->output, json_encode($response, JSON_UNESCAPED_SLASHES).PHP_EOL);
        fflush($this->output);
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array{code: int, message: string, data?: array<string, mixed>}
     */
    private function error(int $code, string $message, ?array $data = null): array
    {
        $error = [
            'code' => $code,
            'message' => $message,
        ];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return $error;
    }
}
