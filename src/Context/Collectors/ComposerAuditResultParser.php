<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

/**
 * Normalizes Composer's audit JSON without dropping non-advisory findings.
 */
final class ComposerAuditResultParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function parse(array $payload, ?int $exitCode = 0): ?array
    {
        // Composer 2.x reserves statuses 1-3 for advisory and abandoned findings.
        if (! in_array($exitCode, [0, 1, 2, 3], true)) {
            return null;
        }

        if (! isset($payload['advisories']) || ! is_array($payload['advisories'])) {
            return null;
        }

        foreach (['ignored-advisories', 'abandoned', 'filter', 'cooldown', 'unreachable-repositories'] as $key) {
            if (array_key_exists($key, $payload) && ! is_array($payload[$key])) {
                return null;
            }
        }

        $advisories = self::advisories($payload['advisories']);
        $ignoredAdvisories = self::advisories($payload['ignored-advisories'] ?? []);

        if ($advisories === null || $ignoredAdvisories === null) {
            return null;
        }

        $abandoned = $payload['abandoned'] ?? [];
        $filtered = $payload['filter'] ?? [];
        $cooldown = $payload['cooldown'] ?? [];
        $unreachable = $payload['unreachable-repositories'] ?? [];

        foreach ($abandoned as $package => $replacement) {
            if (! is_string($package) || $package === '' || (! is_string($replacement) && $replacement !== null)) {
                return null;
            }
        }

        if (array_key_exists('error', $payload)
            || ($exitCode !== 0 && $advisories === [] && $abandoned === [] && $filtered === [] && $cooldown === [] && $unreachable === [])) {
            return null;
        }

        return [
            'available' => true,
            'complete' => $unreachable === [],
            'count' => count($advisories),
            'advisories' => $advisories,
            'ignored_count' => count($ignoredAdvisories),
            'ignored_advisories' => $ignoredAdvisories,
            'abandoned_count' => count($abandoned),
            'abandoned' => $abandoned,
            'filter_count' => count($filtered),
            'filter' => $filtered,
            'cooldown_count' => count($cooldown),
            'cooldown' => $cooldown,
            'unreachable_repositories' => array_values($unreachable),
        ];
    }

    /**
     * @param  array<mixed>  $byPackage
     * @return list<array<string, string>>|null
     */
    private static function advisories(array $byPackage): ?array
    {
        $result = [];

        foreach ($byPackage as $package => $list) {
            if (! is_string($package) || $package === '' || ! is_array($list)) {
                return null;
            }

            foreach ($list as $advisory) {
                if (! is_array($advisory)) {
                    return null;
                }

                $result[] = [
                    'package' => $package,
                    'advisory_id' => self::string($advisory, 'advisoryId'),
                    'title' => self::string($advisory, 'title'),
                    'severity' => self::string($advisory, 'severity'),
                    'affected_versions' => self::string($advisory, 'affectedVersions'),
                    'link' => self::string($advisory, 'link'),
                    'cve' => self::string($advisory, 'cve'),
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $advisory
     */
    private static function string(array $advisory, string $key): string
    {
        return is_string($advisory[$key] ?? null) ? $advisory[$key] : '';
    }
}
