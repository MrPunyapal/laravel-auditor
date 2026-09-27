<?php

declare(strict_types=1);

namespace LaravelAuditor\Support\Agents;

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Support\ApplicationPaths;

/**
 * Registers the Laravel Auditor MCP server in an agent's config file.
 *
 * Supports the JSON configs used by most agents and the TOML file used by
 * Codex. Existing files are merged rather than overwritten; files that are
 * not valid JSON (for example JSON with comments) are left untouched so user
 * config is never corrupted.
 */
final class McpConfigWriter
{
    public function __construct(
        private readonly Filesystem $files,
        private readonly ApplicationPaths $paths,
    ) {}

    /**
     * Register the MCP server for the given agent.
     *
     * Existing `laravel-auditor` entries are left untouched unless `$force`
     * is true. Other servers in the same file are always preserved.
     */
    public function write(Agent $agent, bool $dryRun, bool $force, InstallResult $result): void
    {
        if (! $agent->supportsMcp()) {
            return;
        }

        $path = $this->mcpConfigPath($agent);

        if (str_ends_with(strtolower($path), '.toml')) {
            $this->writeToml($agent, $path, $dryRun, $force, $result);

            return;
        }

        $this->writeJson($agent, $path, $dryRun, $force, $result);
    }

    private function mcpConfigPath(Agent $agent): string
    {
        return base_path($agent->mcpConfigPath);
    }

    private function writeJson(Agent $agent, string $path, bool $dryRun, bool $force, InstallResult $result): void
    {
        $existed = $this->files->exists($path);
        $config = [];

        if ($existed) {
            $decoded = json_decode((string) $this->files->get($path), true);

            if (! is_array($decoded)) {
                $result->skipped[] = $this->paths->relativeToBase($path);

                return;
            }

            $config = $decoded;
        }

        $servers = $config[$agent->mcpConfigKey] ?? [];

        if (! is_array($servers)) {
            $servers = [];
        }

        if ($existed && array_key_exists('laravel-auditor', $servers) && ! $force) {
            $result->skipped[] = $this->paths->relativeToBase($path);

            return;
        }

        $config[$agent->mcpConfigKey] = $servers;
        $config[$agent->mcpConfigKey]['laravel-auditor'] = $this->serverConfig($agent);

        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

        if (! $dryRun) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $encoded);
        }

        if ($existed) {
            $result->updated[] = $this->paths->relativeToBase($path);
        } else {
            $result->created[] = $this->paths->relativeToBase($path);
        }
    }

    private function writeToml(Agent $agent, string $path, bool $dryRun, bool $force, InstallResult $result): void
    {
        $header = "[{$agent->mcpConfigKey}.laravel-auditor]";
        $block = $header.PHP_EOL;
        $block .= 'command = "php"'.PHP_EOL;
        $block .= 'args = ["artisan", "auditor:mcp", "-q"]'.PHP_EOL;

        $existed = $this->files->exists($path);

        if ($existed) {
            $contents = (string) $this->files->get($path);

            if (str_contains($contents, $header)) {
                if (! $force) {
                    $result->skipped[] = $this->paths->relativeToBase($path);

                    return;
                }

                $replaced = preg_replace(
                    '/\['.preg_quote($agent->mcpConfigKey.'.laravel-auditor', '/').'\][^\[]*/s',
                    rtrim($block).PHP_EOL,
                    $contents,
                );

                $block = is_string($replaced) ? rtrim($replaced).PHP_EOL : $block;
            } else {
                $block = rtrim($contents, PHP_EOL).PHP_EOL.PHP_EOL.$block;
            }
        }

        if (! $dryRun) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $block);
        }

        if ($existed) {
            $result->updated[] = $this->paths->relativeToBase($path);
        } else {
            $result->created[] = $this->paths->relativeToBase($path);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serverConfig(Agent $agent): array
    {
        if ($agent->name === 'opencode') {
            return [
                'type' => 'local',
                'enabled' => true,
                'command' => ['php', 'artisan', 'auditor:mcp', '-q'],
            ];
        }

        return [
            'command' => 'php',
            'args' => ['artisan', 'auditor:mcp', '-q'],
        ];
    }
}
