<?php

namespace App\Enum;

use App\Enum\Exceptions\InvalidServiceNameException;

enum NotificationServiceName:string
{
    case DIABETES = "diabetes";
    case WELLNESS = "wellness";
    case MATERNITY = "maternity";

    public static function getValues(): array
    {
        return [
            self::DIABETES,
            self::WELLNESS,
            self::MATERNITY,
        ];
    }

    public static function fromString(string $value): self
    {
        return match ($value) {
            self::DIABETES->value => self::DIABETES,
            self::WELLNESS->value => self::WELLNESS,
            self::MATERNITY->value => self::MATERNITY,
            default => throw new InvalidServiceNameException(
                sprintf("Invalid service name '%s'. Allowed: diabetes, wellness, maternity.", $value)
            ),
        };
    }
}
