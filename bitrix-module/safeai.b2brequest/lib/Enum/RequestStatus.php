<?php

declare(strict_types=1);

namespace SafeAi\B2BRequest\Enum;

enum RequestStatus: string
{
    case NEW = 'new';
    case REVIEW = 'review';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPORTED = 'exported';

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

