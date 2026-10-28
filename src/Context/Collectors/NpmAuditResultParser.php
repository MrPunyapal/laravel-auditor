<?php

declare(strict_types=1);

namespace LaravelAuditor\Context\Collectors;

/**
 * Normalizes npm 7+ audit JSON without executing a package install or fix.
 */
final class NpmAuditResultParser
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public static function parse(array $payload, ?int $exitCode = 0): ?array
    {
        if (! in_array($exitCode, [0, 1], true) || array_key_exists('error', $payload)) {
            return null;
        }

        if (! isset($payload['vulnerabilities']) || ! is_array($payload['vulnerabilities'])) {
            return null;
        }

        $vulnerabilities = [];

        foreach ($payload['vulnerabilities'] as $package => $entry) {
            if (! is_string($package) || $package === '' || ! is_array($entry)
                || ! is_string($entry['severity'] ?? null)
                || ! in_array($entry['severity'], ['info', 'low', 'moderate', 'high', 'critical'], true)
                || ! is_string($entry['range'] ?? null)
                || ! is_array($entry['via'] ?? null)) {
                return null;
            }

            $vulnerabilities[] = [
                'package' => $package,
                'severity' => $entry['severity'],
                'range' => $entry['range'],
                'direct' => ($entry['isDirect'] ?? false) === true,
                'via' => $entry['via'],
                'fix_available' => $entry['fixAvailable'] ?? false,
            ];
        }

        if ($exitCode === 1 && $vulnerabilities === []) {
            return null;
        }

        return [
            'available' => true,
            'count' => count($vulnerabilities),
            'vulnerabilities' => $vulnerabilities,
        ];
    }
}
