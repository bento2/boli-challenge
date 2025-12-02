<?php

namespace App\Service;

interface NotificationServiceInterface
{
    public function sendNotification(string $userId, string $type, string $title, string $body, array $data, string $serviceName): void;
}
