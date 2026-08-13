<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-ARC-001',
        'name' => 'Business logic in controllers',
        'domain' => 'architecture',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'A controller action contains substantial business logic (rules, calculations, orchestration) rather than delegating it.',
        'why_it_matters' => 'Controllers that hold business logic are hard to test in isolation and become tangled as the application grows.',
        'recommendation' => 'Extract the logic into a focused class (action/service/form-request) or a domain method on the model, keeping the controller as a thin HTTP adapter.',
        'evidence' => [
            'The controller file and the size/complexity of the action.',
            'Where the extracted logic should live.',
        ],
        'false_positive_considerations' => [
            'Small amounts of glue code are normal and acceptable in a controller.',
            'Do not recommend "move everything into services" or "repository for every model" — these are cargo-cult patterns.',
            'Report only when the logic is substantial, duplicated, or clearly testable in isolation.',
        ],
        'references' => [
            'https://laravel.com/docs/architecture-concepts',
        ],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-ARC-002',
        'name' => 'Duplicated business logic',
        'domain' => 'architecture',
        'severity' => 'medium',
        'confidence' => 'high',
        'description' => 'The same business rule or behavior is implemented in multiple places with the risk of divergence.',
        'why_it_matters' => 'Duplicated logic drifts over time, causing inconsistent behavior and bugs that are fixed in one place but not another.',
        'recommendation' => 'Extract the shared behavior into a single well-tested location and reuse it from all call sites.',
        'evidence' => [
            'Two or more locations implementing the same rule.',
            'The extracted single source of truth that should be used.',
        ],
        'false_positive_considerations' => [
            'Occasional small similarities are not duplication.',
            'The copies may be intentionally different variants.',
        ],
        'references' => [],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-ARC-003',
        'name' => 'Unnecessary abstraction',
        'domain' => 'architecture',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'An abstraction layer (interface, repository, service, factory) adds indirection without providing real value such as testability, swap-ability, or reused behavior.',
        'why_it_matters' => 'Unneeded abstractions increase maintenance cost and make code harder to follow without adding benefits.',
        'recommendation' => 'Remove the abstraction and use the concrete class directly unless a real extension point is needed.',
        'evidence' => [
            'The abstraction and all its implementations/usages.',
            'Why the abstraction does not currently provide value.',
        ],
        'false_positive_considerations' => [
            'An interface may be justified by an upcoming swap or testing need.',
            'Deferring judgement on common-but-debated patterns (service layer) unless they demonstrably add complexity.',
        ],
        'references' => [],
        'applicability' => [],
    ],
];
