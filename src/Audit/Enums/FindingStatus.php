<?php

declare(strict_types=1);

namespace LaravelAuditor\Audit\Enums;

enum FindingStatus: string
{
    case Open = 'open';

    case Accepted = 'accepted';

    case Dismissed = 'dismissed';

    case Fixed = 'fixed';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
