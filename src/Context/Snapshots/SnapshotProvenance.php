<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Snapshots;

use Illuminate\Foundation\Application;
use LaravelAuditor\Support\GitStatus;
use RuntimeException;

/**
 * Optional capture metadata, not a signature or proof of artifact authenticity.
 */
final class SnapshotProvenance
{
    /**
     * @return array{revision: string|null, dirty: bool|null, routes_cached: bool, php_version: string, laravel_version: string}
     */
    public static function capture(): array
    {
        $git = app(GitStatus::class);
        $revision = $git->revision();
        $status = $revision === null ? null : $git->changedFiles(max: 0);
        /** @var Application $application */
        $application = app();

        return [
            'revision' => $revision,
            'dirty' => $status !== null && $status['available'] ? $status['truncated'] : null,
            'routes_cached' => $application->routesAreCached(),
            'php_version' => PHP_VERSION,
            'laravel_version' => $application->version(),
        ];
    }

    /**
     * @param  array{revision: string|null, dirty: bool|null, routes_cached: bool, php_version: string, laravel_version: string}  $before
     * @return array{revision: string|null, dirty: bool|null, routes_cached: bool, php_version: string, laravel_version: string}
     */
    public static function finish(array $before): array
    {
        $after = self::capture();

        if ($before !== $after) {
            throw new RuntimeException('Capture conditions changed while building the snapshot; capture it again.');
        }

        return $after;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{revision: string|null, dirty: bool|null, routes_cached: bool, php_version: string, laravel_version: string}|null
     */
    public static function read(array $snapshot): ?array
    {
        if (! array_key_exists('provenance', $snapshot)) {
            return null;
        }

        $data = $snapshot['provenance'];

        if (! is_array($data) || array_diff(array_keys($data), ['revision', 'dirty', 'routes_cached', 'php_version', 'laravel_version']) !== []
            || ! array_key_exists('revision', $data) || ! array_key_exists('dirty', $data)
            || ($data['revision'] !== null && (! is_string($data['revision']) || preg_match('/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/D', $data['revision']) !== 1))
            || ($data['dirty'] !== null && ! is_bool($data['dirty']))
            || ($data['revision'] === null && $data['dirty'] !== null)
            || ! is_bool($data['routes_cached'] ?? null)
            || ! is_string($data['php_version'] ?? null) || $data['php_version'] === ''
            || ! is_string($data['laravel_version'] ?? null) || $data['laravel_version'] === '') {
            throw new RuntimeException('Invalid snapshot provenance metadata.');
        }

        return [
            'revision' => $data['revision'],
            'dirty' => $data['dirty'],
            'routes_cached' => $data['routes_cached'],
            'php_version' => $data['php_version'],
            'laravel_version' => $data['laravel_version'],
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, mixed>
     */
    public static function compare(array $before, array $after): array
    {
        $old = self::read($before);
        $new = self::read($after);

        if ($old !== null && $new !== null && $old['routes_cached'] !== $new['routes_cached']) {
            throw new RuntimeException('Snapshot route-cache states differ; capture both with equivalent cache state.');
        }

        return [
            'before' => $old,
            'after' => $new,
            'route_cache_matches' => $old !== null && $new !== null ? true : null,
            'runtime_versions_match' => $old !== null && $new !== null
                ? $old['php_version'] === $new['php_version'] && $old['laravel_version'] === $new['laravel_version']
                : null,
            'artifact_authenticity_verified' => false,
            'environment_equivalence_verified' => false,
            'note' => 'Capture metadata is unsigned; missing or dirty revision state and unrecorded environment inputs limit comparability.',
        ];
    }
}
