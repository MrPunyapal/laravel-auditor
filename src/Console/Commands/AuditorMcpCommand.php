<?php

declare(strict_types=1);

namespace LaravelAuditor\Console\Commands;

use Illuminate\Console\Command;
use LaravelAuditor\MCP\McpServer;
use LaravelAuditor\MCP\McpToolRegistry;

/**
 * Runs the Laravel Auditor MCP server over stdio.
 *
 * Register this server with an agent instead of the default one. For Claude
 * Code: claude mcp add -s local -t stdio laravel-auditor php artisan auditor:mcp -q
 */
class AuditorMcpCommand extends Command
{
    /**
     * The command signature.
     */
    protected $signature = 'auditor:mcp';

    /**
     * The command description.
     */
    protected $description = 'Run the Laravel Auditor MCP server over stdio.';

    public function __construct(
        private readonly McpToolRegistry $tools,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $server = new McpServer(
            tools: $this->tools,
            input: STDIN,
            output: STDOUT,
        );

        return $server->run();
    }
}
