<?php

declare(strict_types=1);

namespace LaravelAuditor\Support;

use Composer\InstalledVersions;

/**
 * Detects whether Laravel Boost is installed in the consuming application.
 */
class BoostDetector
{
    public const string PACKAGE = 'laravel/boost';

    public function isInstalled(): bool
    {
        return InstalledVersions::isInstalled(self::PACKAGE);
    }

    public function version(): ?string
    {
        if (! $this->isInstalled()) {
            return null;
        }

        return InstalledVersions::getPrettyVersion(self::PACKAGE);
    }
}
