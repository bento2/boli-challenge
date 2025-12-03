<?php

namespace App\Enum;

enum NotificationServiceName:string
{
    case DIABETES = "diabetes";
    case WELLNESS = "wellness";
    case MATERNITY = "maternity";

    public static function values(): array
    {
        return [
            self::DIABETES,
            self::WELLNESS,
            self::MATERNITY,
        ];
    }

}
