<?php

declare(strict_types=1);

namespace LaravelAuditor\Support;

use Composer\InstalledVersions;
use Throwable;

/**
 * Resolves the installed package version from Composer metadata.
 */
final class PackageVersion
{
    public const string PACKAGE = 'mrpunyapal/laravel-auditor';

    public static function current(): string
    {
        try {
            return InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'dev';
        } catch (Throwable) {
            return 'dev';
        }
    }
}
