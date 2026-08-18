<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

use Illuminate\Filesystem\Filesystem;
use LaravelAuditor\Context\ContextCollector;
use SplFileInfo;
use Throwable;

/**
 * Collects the available configuration keys from the config directory.
 *
 * Keys are read from the configuration files. A small set of non-secret
 * values is included so agents can inspect debug, environment, and driver
 * settings without dumping credentials.
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

            foreach ($this->topLevelKeysInFile($file, $root) as $key) {
                $keys[] = $key;
            }
        }

        sort($keys, SORT_STRING);

        return [
            'count' => count($keys),
            'keys' => $keys,
            'files' => $files,
            'safe_values' => $this->safeValues(),
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
    private function topLevelKeysInFile(string $file, string $root): array
    {
        $path = config_path($file);

        if (! $this->files->exists($path)) {
            return [];
        }

        try {
            $data = require $path;
        } catch (Throwable) {
            return [];
        }

        if (! is_array($data)) {
            return [];
        }

        $keys = [];

        foreach (array_keys($data) as $key) {
            $keys[] = $root.'.'.$key;
        }

        return $keys;
    }

    /**
     * @return array<string, mixed>
     */
    private function safeValues(): array
    {
        $safe = [];

        foreach ([
            'app.name',
            'app.env',
            'app.debug',
            'app.timezone',
            'session.driver',
            'session.secure',
            'queue.default',
            'cache.default',
            'database.default',
            'logging.default',
        ] as $key) {
            $value = config($key);

            if (is_scalar($value) || $value === null) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }
}
