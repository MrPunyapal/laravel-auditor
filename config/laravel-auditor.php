<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled Audit Domains
    |--------------------------------------------------------------------------
    |
    | Domains scoped for an audit. The agent will use the workflow skill to
    | select the relevant domains for the actual application, but this list
    | defines the default scope and the domains advertised in reports.
    |
    */

    'domains' => [
        'security',
        'performance',
        'architecture',
        'database',
        'testing',
        'conventions',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rule Discovery
    |--------------------------------------------------------------------------
    |
    | Directories (relative to the application base) that contain additional
    | rule definition files. Rules follow the same schema as the package's
    | built-in rules.
    |
    */

    'rules' => [
        // base_path('auditor/rules'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Standalone Agent Resources Target
    |--------------------------------------------------------------------------
    |
    | Where the standalone installer publishes agent-facing resources when
    | Laravel Boost is not present. When Boost is installed, resources are
    | consumed through Boost instead.
    |
    */

    'resources_target' => '.ai',

    /*
    |--------------------------------------------------------------------------
    | Default Agents
    |--------------------------------------------------------------------------
    |
    | Agents configured when `auditor:install` runs non-interactively. When
    | empty, the installer detects agents from project markers and falls back
    | to all supported agents. Accepted values: opencode, claude_code, cursor,
    | copilot, gemini, codex, junie, zed.
    |
    */

    'agents' => [],

    /*
    |--------------------------------------------------------------------------
    | Context Collector Options
    |--------------------------------------------------------------------------
    |
    | Toggles for individual context collectors. These affect read-only data
    | gathering only; nothing here ever mutates the audited application.
    |
    */

    'context' => [
        /*
        | Best-effort `composer audit` call from the dependencies collector.
        | Disable to avoid shelling out to composer during context gathering.
        */
        'composer_audit' => true,

        /*
        | Best-effort `pest --list-tests` / `phpunit --list-tests` call from
        | the tests collector to report accurate test case counts. Disable to
        | avoid shelling out to the test runner; the collector then falls back
        | to counting test files.
        */
        'test_listing' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Report Defaults
    |--------------------------------------------------------------------------
    |
    | Default renderer used by `auditor:report` when --format is omitted.
    |
    */

    'report' => [
        'format' => 'markdown',
    ],

];
