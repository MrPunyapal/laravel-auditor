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

];
