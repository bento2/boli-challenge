<?php

namespace App\Service;

interface NotificationServiceInterface
{
    public function setDryRun(bool $dryRun = false): void;
    public function sendNotification(string $userId, string $type, string $title, string $body = "", array $data = [], string $serviceName = ""): bool;
}
