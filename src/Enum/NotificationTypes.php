<?php
// src/Enum/NotificationType.php

namespace App\Enum;

use App\Enum\Exceptions\InvalidTypeException;

enum NotificationTypes: string
{
    case ALERT = 'alert';
    case REMINDER = 'reminder';
    case INFO = 'info';

    // Méthode utilitaire pour obtenir toutes les valeurs pour la validation
    public static function getValues(): array
    {
        return [
            self::ALERT,
            self::REMINDER,
            self::INFO,
        ];
    }

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
