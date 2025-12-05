<?php

namespace App\Enum;

use App\Enum\Exceptions\InvalidStatusException;

enum NotificationStatus: string
{
    case PENDING = 'pending';
    case SENT = 'sent';
    case FAILED = 'failed';

    public static function getValues(): array
    {
        return [
            self::PENDING,
            self::SENT,
            self::FAILED,
        ];
    }

    public static function fromString(string $value): self
    {
        return match ($value) {
            self::PENDING->value => self::PENDING,
            self::SENT->value => self::SENT,
            self::FAILED->value => self::FAILED,
            default => throw new InvalidStatusException(
                sprintf("Invalid notification status '%s'. Allowed: pending, sent, failed.", $value)
            ),
        };
    }
}
