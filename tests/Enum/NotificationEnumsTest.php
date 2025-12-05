<?php

namespace App\Tests\Enum;

use App\Enum\NotificationServiceName;
use App\Enum\NotificationStatus;
use App\Enum\NotificationTypes;
use App\Enum\Exceptions\InvalidTypeException;
use App\Enum\Exceptions\InvalidStatusException;
use App\Enum\Exceptions\InvalidServiceNameException;
use PHPUnit\Framework\TestCase;

class NotificationEnumsTest extends TestCase
{
    public function testNotificationTypesHasCorrectCases(): void
    {
        $this->assertEquals('alert', NotificationTypes::ALERT->value);
        $this->assertEquals('reminder', NotificationTypes::REMINDER->value);
        $this->assertEquals('info', NotificationTypes::INFO->value);
    }

    public function testNotificationTypesGetValues(): void
    {
        $values = NotificationTypes::getValues();

        $this->assertIsArray($values);
        $this->assertCount(3, $values);
        $this->assertContains(NotificationTypes::ALERT, $values);
        $this->assertContains(NotificationTypes::REMINDER, $values);
        $this->assertContains(NotificationTypes::INFO, $values);
    }

    public function testNotificationTypesCases(): void
    {
        $cases = NotificationTypes::cases();

        $this->assertCount(3, $cases);
        $this->assertEquals(NotificationTypes::ALERT, $cases[0]);
        $this->assertEquals(NotificationTypes::REMINDER, $cases[1]);
        $this->assertEquals(NotificationTypes::INFO, $cases[2]);
    }

    public function testNotificationTypesFromString(): void
    {
        $this->assertEquals(NotificationTypes::ALERT, NotificationTypes::fromString('alert'));
        $this->assertEquals(NotificationTypes::REMINDER, NotificationTypes::fromString('reminder'));
        $this->assertEquals(NotificationTypes::INFO, NotificationTypes::fromString('info'));
    }

    public function testNotificationTypesFromStringThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(InvalidTypeException::class);
        $this->expectExceptionMessage("Invalid notification type 'invalid'. Allowed: alert, reminder, info.");
        NotificationTypes::fromString('invalid');
    }

    public function testNotificationStatusHasCorrectCases(): void
    {
        $this->assertEquals('pending', NotificationStatus::PENDING->value);
        $this->assertEquals('sent', NotificationStatus::SENT->value);
        $this->assertEquals('failed', NotificationStatus::FAILED->value);
    }

    public function testNotificationStatusGetValues(): void
    {
        $values = NotificationStatus::getValues();

        $this->assertIsArray($values);
        $this->assertCount(3, $values);
        $this->assertContains(NotificationStatus::PENDING, $values);
        $this->assertContains(NotificationStatus::SENT, $values);
        $this->assertContains(NotificationStatus::FAILED, $values);
    }

    public function testNotificationStatusCases(): void
    {
        $cases = NotificationStatus::cases();

        $this->assertCount(3, $cases);
        $this->assertEquals(NotificationStatus::PENDING, $cases[0]);
        $this->assertEquals(NotificationStatus::SENT, $cases[1]);
        $this->assertEquals(NotificationStatus::FAILED, $cases[2]);
    }

    public function testNotificationStatusFromString(): void
    {
        $this->assertEquals(NotificationStatus::PENDING, NotificationStatus::fromString('pending'));
        $this->assertEquals(NotificationStatus::SENT, NotificationStatus::fromString('sent'));
        $this->assertEquals(NotificationStatus::FAILED, NotificationStatus::fromString('failed'));
    }

    public function testNotificationStatusFromStringThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(InvalidStatusException::class);
        $this->expectExceptionMessage("Invalid notification status 'unknown'. Allowed: pending, sent, failed.");
        NotificationStatus::fromString('unknown');
    }

    public function testNotificationServiceNameHasCorrectCases(): void
    {
        $this->assertEquals('diabetes', NotificationServiceName::DIABETES->value);
        $this->assertEquals('wellness', NotificationServiceName::WELLNESS->value);
        $this->assertEquals('maternity', NotificationServiceName::MATERNITY->value);
    }

    public function testNotificationServiceNameGetValues(): void
    {
        $values = NotificationServiceName::getValues();

        $this->assertIsArray($values);
        $this->assertCount(3, $values);
        $this->assertContains(NotificationServiceName::DIABETES, $values);
        $this->assertContains(NotificationServiceName::WELLNESS, $values);
        $this->assertContains(NotificationServiceName::MATERNITY, $values);
    }

    public function testNotificationServiceNameCases(): void
    {
        $cases = NotificationServiceName::cases();

        $this->assertCount(3, $cases);
        $this->assertEquals(NotificationServiceName::DIABETES, $cases[0]);
        $this->assertEquals(NotificationServiceName::WELLNESS, $cases[1]);
        $this->assertEquals(NotificationServiceName::MATERNITY, $cases[2]);
    }

    public function testNotificationServiceNameFromString(): void
    {
        $this->assertEquals(NotificationServiceName::DIABETES, NotificationServiceName::fromString('diabetes'));
        $this->assertEquals(NotificationServiceName::WELLNESS, NotificationServiceName::fromString('wellness'));
        $this->assertEquals(NotificationServiceName::MATERNITY, NotificationServiceName::fromString('maternity'));
    }

    public function testNotificationServiceNameFromStringThrowsExceptionForInvalidValue(): void
    {
        $this->expectException(InvalidServiceNameException::class);
        $this->expectExceptionMessage("Invalid service name 'cardiology'. Allowed: diabetes, wellness, maternity.");
        NotificationServiceName::fromString('cardiology');
    }

    public function testNotificationTypesFromMethodUsesNativeFrom(): void
    {
        // Test that the native from() method also works (PHP 8.1+ backed enums)
        $this->assertEquals(NotificationTypes::ALERT, NotificationTypes::from('alert'));
        $this->assertEquals(NotificationTypes::REMINDER, NotificationTypes::from('reminder'));
        $this->assertEquals(NotificationTypes::INFO, NotificationTypes::from('info'));
    }

    public function testNotificationStatusFromMethodUsesNativeFrom(): void
    {
        $this->assertEquals(NotificationStatus::PENDING, NotificationStatus::from('pending'));
        $this->assertEquals(NotificationStatus::SENT, NotificationStatus::from('sent'));
        $this->assertEquals(NotificationStatus::FAILED, NotificationStatus::from('failed'));
    }

    public function testNotificationServiceNameFromMethodUsesNativeFrom(): void
    {
        $this->assertEquals(NotificationServiceName::DIABETES, NotificationServiceName::from('diabetes'));
        $this->assertEquals(NotificationServiceName::WELLNESS, NotificationServiceName::from('wellness'));
        $this->assertEquals(NotificationServiceName::MATERNITY, NotificationServiceName::from('maternity'));
    }
}
