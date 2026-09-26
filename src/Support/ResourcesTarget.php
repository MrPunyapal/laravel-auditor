<?php

declare(strict_types=1);

namespace LaravelAuditor\Support;

/**
 * Resolves where standalone agent resources live.
 *
 * Single source for the `laravel-auditor.resources_target` config so the
 * service provider, installer, and status command cannot drift.
 */
final class ResourcesTarget
{
    public static function resolve(): string
    {
        $target = trim((string) config('laravel-auditor.resources_target', '.ai'), '/\\');

        return $target !== '' ? $target : '.ai';
    }
}
