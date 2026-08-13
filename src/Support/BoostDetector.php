<?php

declare(strict_types=1);

namespace LaravelAuditor\Support;

use Composer\InstalledVersions;

/**
 * Detects whether Laravel Boost is installed in the consuming application.
 */
final class BoostDetector
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

    public function supportsThirdPartyResources(): bool
    {
        // Boost loads third-party guidelines/skills from resources/boost in
        // installed packages since v0.x. If Boost is installed, we rely on
        // its installer consuming our packaged resources.
        return $this->isInstalled();
    }
}
