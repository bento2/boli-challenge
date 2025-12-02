<?php
// src/Enum/NotificationType.php

namespace App\Enum;

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
}
