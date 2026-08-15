<?php

declare(strict_types=1);

return [
    [
        'id' => 'AUD-DSA-001',
        'name' => 'Invalid state combinations',
        'domain' => 'architecture',
        'severity' => 'medium',
        'confidence' => 'high',
        'description' => 'Scattered booleans or nullable fields permit invalid combinations that a state machine or discriminated union would make unrepresentable.',
        'why_it_matters' => 'Invalid combinations become production bugs that no amount of local ifs can consistently prevent.',
        'recommendation' => 'Replace the flag set with an explicit state (enum, status column, or value object) so illegal combinations cannot be constructed.',
        'evidence' => [
            'The fields and the illegal combination they currently allow.',
            'A call site that has to defend against that combination.',
        ],
        'false_positive_considerations' => [
            'Independent flags that truly combine freely are not invalid state.',
            'Do not invent a state machine for two obvious booleans.',
        ],
        'references' => [],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-DSA-002',
        'name' => 'Unclear state or behavior ownership',
        'domain' => 'architecture',
        'severity' => 'medium',
        'confidence' => 'medium',
        'description' => 'The same state or behavior is owned in multiple modules, so updates can diverge or go stale.',
        'why_it_matters' => 'Split ownership is how lost-updates and contradictory state show up.',
        'recommendation' => 'Give one module the write path. Everyone else reads from that source of truth.',
        'evidence' => [
            'The two owners and the state they both mutate or cache.',
        ],
        'false_positive_considerations' => [
            'Read models and projections are fine when one writer is obvious.',
        ],
        'references' => [],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-DSA-003',
        'name' => 'Duplicated branching that a registry would remove',
        'domain' => 'architecture',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'The same type/command/status switch is copied across files when a small map or registry would be the organizing model.',
        'why_it_matters' => 'Copied branches drift. The next case gets added in one place and missed in another.',
        'recommendation' => 'Collapse the copies into one registry or map. Do not add an interface if a plain array is enough.',
        'evidence' => [
            'Two or more switches over the same set of cases.',
        ],
        'false_positive_considerations' => [
            'A single switch in one file is already the simple model.',
        ],
        'references' => [],
        'applicability' => [],
    ],
    [
        'id' => 'AUD-DSA-004',
        'name' => 'Repeated scans that need an index',
        'domain' => 'performance',
        'severity' => 'low',
        'confidence' => 'medium',
        'description' => 'The same collection is repeatedly scanned or transformed where a keyed index, groupBy, or unique map would make the algorithm obvious.',
        'why_it_matters' => 'Repeated scans hide O(n^2) behavior and make the intent harder to read.',
        'recommendation' => 'Index once by the lookup key. Keep the boring loop if the set is tiny and scanned once.',
        'evidence' => [
            'The scan sites and the key they keep searching for.',
        ],
        'false_positive_considerations' => [
            'A single pass over a small in-memory list is not a finding.',
        ],
        'references' => [],
        'applicability' => [],
    ],
];
