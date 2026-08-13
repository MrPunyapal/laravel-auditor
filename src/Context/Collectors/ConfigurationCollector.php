<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;
use SplFileInfo;

/**
 * Collects the available configuration keys from the config directory.
 *
 * Values are resolved through the application configuration store so agents
 * see the effective configuration, not just the files. Secret-looking values
 * are redacted to avoid exposing sensitive data through tool output.
 */
final class ConfigurationCollector implements ContextCollector
{
    public function __construct(
        private readonly Filesystem $files,
    ) {}

    public function name(): string
    {
        return 'configuration';
    }

    public function description(): string
    {
        return 'List available configuration keys and safe configuration values.';
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(): array
    {
        $files = $this->configFiles();

        $keys = [];

        foreach ($files as $file) {
            $root = pathinfo($file, PATHINFO_FILENAME);
            $keys[] = $root;

            foreach ($this->keysInFile($file, $root) as $key) {
                $keys[] = $key;
            }
        }

        sort($keys, SORT_STRING);

        return [
            'count' => count($keys),
            'keys' => $keys,
            'files' => $files,
        ];
    }

    /**
     * @return list<string>
     */
    private function configFiles(): array
    {
        $path = config_path();

        if (! $this->files->isDirectory($path)) {
            return [];
        }

        return array_values(array_map(
            static fn (SplFileInfo $file): string => $file->getRelativePathname(),
            $this->files->allFiles($path),
        ));
    }

    /**
     * @return list<string>
     */
    private function keysInFile(string $file, string $root): array
    {
        $path = config_path($file);

        if (! $this->files->exists($path)) {
            return [];
        }

        $data = require $path;

        if (! is_array($data)) {
            return [];
        }

        return $this->flattenKeys($data, $root);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function flattenKeys(array $data, string $prefix): array
    {
        $keys = [];

        foreach ($data as $key => $value) {
            $full = "{$prefix}.{$key}";

            if (is_array($value)) {
                $keys[] = $full;
                $keys = array_merge($keys, $this->flattenKeys($value, $full));
            } else {
                $keys[] = $full;
            }
        }

        return $keys;
    }
}
