<?php

declare(strict_types=1);

namespace LaravelAuditor\Support\Agents;

use Illuminate\Filesystem\Filesystem;

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
    public function __construct(private readonly Filesystem $files) {}

    /**
     * Register the MCP server for the given agent.
     *
     * @param  list<string>  $created
     * @param  list<string>  $updated
     * @param  list<string>  $skipped
     * @return array{list<string>, list<string>, list<string>}
     */
    public function write(Agent $agent, bool $dryRun, array $created, array $updated, array $skipped): array
    {
        if (! $agent->supportsMcp()) {
            return [$created, $updated, $skipped];
        }

        $path = $this->mcpConfigPath($agent);

        if (str_ends_with(strtolower($path), '.toml')) {
            return $this->writeToml($agent, $path, $dryRun, $created, $updated, $skipped);
        }

        return $this->writeJson($agent, $path, $dryRun, $created, $updated, $skipped);
    }

    private function mcpConfigPath(Agent $agent): string
    {
        return base_path($agent->mcpConfigPath);
    }

    /**
     * @param  list<string>  $created
     * @param  list<string>  $updated
     * @param  list<string>  $skipped
     * @return array{list<string>, list<string>, list<string>}
     */
    private function writeJson(Agent $agent, string $path, bool $dryRun, array $created, array $updated, array $skipped): array
    {
        $existed = $this->files->exists($path);
        $config = [];

        if ($existed) {
            $decoded = json_decode((string) $this->files->get($path), true);

            if (! is_array($decoded)) {
                $skipped[] = $this->relative($path);

                return [$created, $updated, $skipped];
            }

            $config = $decoded;
        }

        $config[$agent->mcpConfigKey] = $config[$agent->mcpConfigKey] ?? [];
        $config[$agent->mcpConfigKey]['laravel-auditor'] = $this->serverConfig($agent);

        $encoded = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL;

        if (! $dryRun) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $encoded);
        }

        $existed ? $updated[] = $this->relative($path) : $created[] = $this->relative($path);

        return [$created, $updated, $skipped];
    }

    /**
     * @param  list<string>  $created
     * @param  list<string>  $updated
     * @param  list<string>  $skipped
     * @return array{list<string>, list<string>, list<string>}
     */
    private function writeToml(Agent $agent, string $path, bool $dryRun, array $created, array $updated, array $skipped): array
    {
        $key = $agent->mcpConfigKey.'laravel-auditor';
        $block = "[{$agent->mcpConfigKey}.laravel-auditor]".PHP_EOL;
        $block .= 'command = "php"'.PHP_EOL;
        $block .= 'args = ["artisan", "auditor:mcp"]'.PHP_EOL;

        $existed = $this->files->exists($path);

        if ($existed) {
            $contents = (string) $this->files->get($path);

            if (str_contains($contents, $key)) {
                $updated[] = $this->relative($path);

                return [$created, $updated, $skipped];
            }

            $block = rtrim($contents, PHP_EOL).PHP_EOL.PHP_EOL.$block;
        }

        if (! $dryRun) {
            $this->files->ensureDirectoryExists(dirname($path));
            $this->files->put($path, $block);
        }

        $existed ? $updated[] = $this->relative($path) : $created[] = $this->relative($path);

        return [$created, $updated, $skipped];
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
                'command' => ['php', 'artisan', 'auditor:mcp'],
            ];
        }

        return [
            'command' => 'php',
            'args' => ['artisan', 'auditor:mcp'],
        ];
    }

    private function relative(string $path): string
    {
        $base = str_replace('\\', '/', base_path());
        $path = str_replace('\\', '/', $path);

        return ltrim(substr($path, strlen($base)), '/');
    }
}
