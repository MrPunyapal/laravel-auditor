<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Enums;

enum Confidence: string
{
    case Confirmed = 'confirmed';

    case High = 'high';

    case Medium = 'medium';

    case Low = 'low';

    /**
     * A numeric ordering used to sort findings, lowest confidence first.
     */
    public function weight(): int
    {
        return match ($this) {
            self::Confirmed => 4,
            self::High => 3,
            self::Medium => 2,
            self::Low => 1,
        };
    }

    /**
     * The human-readable label for the confidence level.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
