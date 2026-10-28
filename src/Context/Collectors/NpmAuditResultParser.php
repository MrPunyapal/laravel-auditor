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
    public static function parse(array $payload): ?array
    {
        if (! isset($payload['vulnerabilities']) || ! is_array($payload['vulnerabilities'])) {
            return null;
        }

        $vulnerabilities = [];

        foreach ($payload['vulnerabilities'] as $package => $entry) {
            if (! is_string($package) || ! is_array($entry)) {
                return null;
            }

            $vulnerabilities[] = [
                'package' => $package,
                'severity' => is_string($entry['severity'] ?? null) ? $entry['severity'] : '',
                'range' => is_string($entry['range'] ?? null) ? $entry['range'] : '',
                'direct' => ($entry['isDirect'] ?? false) === true,
                'via' => is_array($entry['via'] ?? null) ? $entry['via'] : [],
                'fix_available' => $entry['fixAvailable'] ?? false,
            ];
        }

        return [
            'available' => true,
            'count' => count($vulnerabilities),
            'vulnerabilities' => $vulnerabilities,
        ];
    }
}
