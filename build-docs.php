<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Docsmith\Docsmith;

$siteUrl = getenv('DOCS_SITE_URL') ?: 'https://mrpunyapal.github.io/laravel-auditor';
$editBranch = getenv('DOCS_EDIT_BRANCH') ?: 'main';
$baseUrl = getenv('DOCS_BASE_URL') ?: '/laravel-auditor/';

Docsmith::make()
    ->source(__DIR__.'/md')
    ->output(__DIR__.'/docs')
    ->title('Laravel Auditor')
    ->description('Evidence-based, agent-agnostic auditing tools and methodology for Laravel applications.')
    ->repositoryUrl('https://github.com/mrpunyapal/laravel-auditor')
    ->siteUrl($siteUrl)
    ->editBranch($editBranch)
    ->baseUrl($baseUrl)
    ->rightSidebar()
    ->build();
