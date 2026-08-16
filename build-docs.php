<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Docsmith\Docsmith;
use Docsmith\Support\OgCaptureEnvironment;

$siteUrl = getenv('DOCS_SITE_URL') ?: 'https://mrpunyapal.github.io/laravel-auditor';
$editBranch = getenv('DOCS_EDIT_BRANCH') ?: 'main';
$baseUrl = getenv('DOCS_BASE_URL') ?: '/laravel-auditor/';

$output = __DIR__.'/docs';

$environment = new OgCaptureEnvironment;
$nodeProject = $environment->resolveNodeProjectRoot($output);

$captureOg = ! in_array(
    strtolower((string) getenv('DOCS_CAPTURE_OG')),
    ['0', 'false', 'no'],
    true,
) && $environment->hasNode() && $environment->hasNpm()
    && $environment->isPlaywrightPackageInstalled($nodeProject)
    && $environment->isCapturistInstalled($nodeProject)
    && $environment->isPlaywrightBrowserInstalled($nodeProject);

$builder = Docsmith::make()
    ->source(__DIR__.'/md')
    ->output($output)
    ->title('Laravel Auditor')
    ->description('Evidence-based, agent-agnostic auditing tools and methodology for Laravel applications.')
    ->repositoryUrl('https://github.com/mrpunyapal/laravel-auditor')
    ->siteUrl($siteUrl)
    ->editBranch($editBranch)
    ->baseUrl($baseUrl)
    ->accentColor('#ff2d20')
    ->accentColorDark('#ff746c')
    ->favicon(__DIR__.'/resources/docs/favicon.svg')
    ->ogTemplate(__DIR__.'/resources/docs/og-card.html', scope: 'per-page')
    ->rightSidebar()
    ->captureOg($captureOg);

if (! $captureOg) {
    echo "[Docsmith] Open Graph image capture skipped (DOCS_CAPTURE_OG=0 or Node/capturist/Playwright not ready).\n";
    echo "[Docsmith] Install with: npm install && npx playwright install chromium\n";
}

$builder->build();
