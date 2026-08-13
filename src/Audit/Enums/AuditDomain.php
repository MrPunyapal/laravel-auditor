<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Enums;

enum AuditDomain: string
{
    case Security = 'security';

    case Performance = 'performance';

    case Architecture = 'architecture';

    case Database = 'database';

    case Testing = 'testing';

    case Conventions = 'conventions';

    /**
     * The human-readable label for the domain.
     */
    public function label(): string
    {
        return match ($this) {
            self::Security => 'Security',
            self::Performance => 'Performance',
            self::Architecture => 'Architecture',
            self::Database => 'Database',
            self::Testing => 'Testing',
            self::Conventions => 'Laravel conventions',
        };
    }

    /**
     * A short description of the domain's purpose.
     */
    public function description(): string
    {
        return match ($this) {
            self::Security => 'Authorization, validation, sensitive data handling, and other security boundaries.',
            self::Performance => 'Query efficiency, eager loading, request-lifecycle work, caching, and queues.',
            self::Architecture => 'Application boundaries, responsibility, coupling, and maintainability.',
            self::Database => 'Schema, indexes, relationships, migrations, and query efficiency.',
            self::Testing => 'Test coverage quality, meaningful assertions, and testing conventions.',
            self::Conventions => 'Laravel framework conventions, idiomatic usage, and version-appropriate APIs.',
        };
    }

    /**
     * @return list<self>
     */
    public static function core(): array
    {
        return [
            self::Security,
            self::Performance,
            self::Architecture,
            self::Database,
            self::Testing,
            self::Conventions,
        ];
    }
}
