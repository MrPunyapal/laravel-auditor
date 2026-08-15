<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-DEP-001',
        'name' => 'Known vulnerable dependency',
        'domain' => 'security',
        'severity' => 'high',
        'confidence' => 'high',
        'description' => 'An installed composer package has a known security advisory reported by `composer audit` for the installed version.',
        'why_it_matters' => 'Known-vulnerability packages are the most commonly exploited dependency risk because fixes and PoCs are public.',
        'recommendation' => 'Update the affected package to a patched version, or replace it if no fix exists, and re-run `composer audit` to confirm the advisory clears.',
        'evidence' => [
            'The `composer audit --format=json` output naming the package, installed version, and advisory.',
            'The `composer.lock` entry confirming the installed version.',
        ],
        'false_positive_considerations' => [
            'The advisory may not be reachable given how the package is used, but it should still be remediated.',
            '`composer audit` may be unavailable offline; note that the check could not run.',
        ],
        'references' => [
            'https://getcomposer.org/doc/03-cli.md#audit',
            'https://github.com/advisories',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-DEP-002',
        'name' => 'Abandoned dependency',
        'domain' => 'conventions',
        'severity' => 'low',
        'confidence' => 'high',
        'description' => 'An installed package is abandoned (`composer show` reports "Abandoned") and the app depends on it for a non-trivial feature.',
        'why_it_matters' => 'Abandoned packages receive no security or compatibility fixes, which increases future maintenance and risk.',
        'recommendation' => 'Plan a migration to a maintained fork or an alternative package and track it as a technical-debt item.',
        'evidence' => [
            'The `composer show` output listing the package as abandoned.',
            'Where the package is used in the application.',
        ],
        'false_positive_considerations' => [
            'A package may be abandoned yet stable and safe if it does not process untrusted input.',
            'Dev-only tools that are still functional may not be urgent to replace.',
        ],
        'references' => [
            'https://getcomposer.org/doc/03-cli.md#show',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-DEP-003',
        'name' => 'License risk in a commercial product',
        'domain' => 'conventions',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'A dependency uses a license (GPL/AGPL, or a non-commercial/commercial source-available license) that can conflict with distributing a proprietary or commercial product.',
        'why_it_matters' => 'License incompatibilities can force open-sourcing proprietary code or incur legal/compliance cost at release time.',
        'recommendation' => 'Review the licenses of dependencies that ship with the product and confirm they are compatible with the intended distribution model, or replace incompatible ones.',
        'evidence' => [
            'The package and its declared license.',
            'The product\'s intended distribution model.',
        ],
        'false_positive_considerations' => [
            'Many GPL-licensed packages are only dev tools or run server-side and do not trigger distribution obligations.',
            'An internal, non-distributed product may have no licensing conflict.',
        ],
        'references' => [
            'https://github.com/composer/composer/issues/2708',
            'https://choosealicense.com/',
        ],
        'applicability' => [],
    ],
];
