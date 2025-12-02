<?php

namespace App\Service;
use App\Service\Exception\InvalidTypeException;

enum NotificationType: string
{
    case ALERT = 'alert';
    case REMINDER = 'reminder';
    case INFO = 'info';

    public static function fromString(string $value): self
    {
        return match ($value) {
            self::ALERT->value => self::ALERT,
            self::REMINDER->value => self::REMINDER,
            self::INFO->value => self::INFO,
            default => throw new InvalidTypeException(
                sprintf("Invalid notification type '%s'. Allowed: alert, reminder, info.", $value)
            ),
        };
    }
}
