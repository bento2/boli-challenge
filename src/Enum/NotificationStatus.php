<?php

namespace App\Enum;

enum NotificationStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';

    public static function values(): array{
        return [
            self::PENDING,
            self::SENT,
            self::FAILED,
        ];
    }

}
