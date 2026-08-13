<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Enums;

enum Severity: string
{
    case Critical = 'critical';

    case High = 'high';

    case Medium = 'medium';

    case Low = 'low';

    case Info = 'info';

    /**
     * A numeric ordering used to sort findings, lowest severity first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Critical => 5,
            self::High => 4,
            self::Medium => 3,
            self::Low => 2,
            self::Info => 1,
        };
    }

    /**
     * The human-readable label for the severity level.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
