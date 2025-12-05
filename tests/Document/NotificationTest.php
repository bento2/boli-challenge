<?php

namespace App\Tests\Document;

use App\Document\Notification;
use App\Enum\NotificationServiceName;
use App\Enum\NotificationStatus;
use App\Enum\NotificationTypes;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class NotificationTest extends TestCase
{
    public function testConstructorSetsPropertiesCorrectly(): void
    {
        $userId = 'user-123';
        $type = NotificationTypes::ALERT->value;
        $title = 'Test Notification';
        $body = 'This is a test message';
        $serviceName = NotificationServiceName::DIABETES->value;
        $data = ['key' => 'value'];

        $notification = new Notification($userId, $type, $title, $body, $serviceName, $data);

        $this->assertEquals($userId, $notification->getUserId());
        $this->assertEquals($type, $notification->getType());
        $this->assertEquals($title, $notification->getTitle());
        $this->assertEquals($body, $notification->getBody());
        $this->assertEquals($serviceName, $notification->getServiceName());
        $this->assertEquals($data, $notification->getData());
        $this->assertInstanceOf(\DateTimeInterface::class, $notification->getCreatedAt());
    }

    public function testConstructorWithoutDataSetsEmptyArray(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::WELLNESS->value
        );

        $this->assertEquals([], $notification->getData());
    }

    public function testDefaultValues(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::WELLNESS->value
        );

        $this->assertNull($notification->getId());
        $this->assertEquals('pending', $notification->getStatus());
        $this->assertNull($notification->getSentAt());
        $this->assertNull($notification->getReadAt());
    }

    public function testSetAndGetId(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $id = '507f1f77bcf86cd799439011';
        $result = $notification->setId($id);

        $this->assertSame($notification, $result); // Test fluent interface
        $this->assertEquals($id, $notification->getId());
    }

    public function testSetAndGetUserId(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $newUserId = 'user-456';
        $result = $notification->setUserId($newUserId);

        $this->assertSame($notification, $result);
        $this->assertEquals($newUserId, $notification->getUserId());
    }

    public function testSetAndGetType(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $newType = NotificationTypes::REMINDER->value;
        $result = $notification->setType($newType);

        $this->assertSame($notification, $result);
        $this->assertEquals($newType, $notification->getType());
    }

    public function testSetAndGetTitle(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $newTitle = 'Updated Title';
        $result = $notification->setTitle($newTitle);

        $this->assertSame($notification, $result);
        $this->assertEquals($newTitle, $notification->getTitle());
    }

    public function testSetAndGetBody(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $newBody = 'Updated body text';
        $result = $notification->setBody($newBody);

        $this->assertSame($notification, $result);
        $this->assertEquals($newBody, $notification->getBody());
    }

    public function testSetAndGetData(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $newData = ['foo' => 'bar', 'count' => 42];
        $result = $notification->setData($newData);

        $this->assertSame($notification, $result);
        $this->assertEquals($newData, $notification->getData());
    }

    public function testSetAndGetStatus(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $newStatus = NotificationStatus::SENT->value;
        $result = $notification->setStatus($newStatus);

        $this->assertSame($notification, $result);
        $this->assertEquals($newStatus, $notification->getStatus());
    }

    public function testSetAndGetSentAt(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $sentAt = new DateTimeImmutable('2024-01-15 10:30:00');
        $result = $notification->setSentAt($sentAt);

        $this->assertSame($notification, $result);
        $this->assertEquals($sentAt, $notification->getSentAt());
    }

    public function testSetAndGetCreatedAt(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $createdAt = new DateTimeImmutable('2024-01-01 00:00:00');
        $result = $notification->setCreatedAt($createdAt);

        $this->assertSame($notification, $result);
        $this->assertEquals($createdAt, $notification->getCreatedAt());
    }

    public function testSetAndGetServiceName(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $newServiceName = NotificationServiceName::MATERNITY->value;
        $result = $notification->setServiceName($newServiceName);

        $this->assertSame($notification, $result);
        $this->assertEquals($newServiceName, $notification->getServiceName());
    }

    public function testSetAndGetReadAt(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $readAt = new DateTimeImmutable('2024-01-20 15:45:00');
        $result = $notification->setReadAt($readAt);

        $this->assertSame($notification, $result);
        $this->assertEquals($readAt, $notification->getReadAt());
    }

    public function testSetSentAtCanBeNull(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $notification->setSentAt(new DateTimeImmutable());
        $notification->setSentAt(null);

        $this->assertNull($notification->getSentAt());
    }

    public function testSetReadAtCanBeNull(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $notification->setReadAt(new DateTimeImmutable());
        $notification->setReadAt(null);

        $this->assertNull($notification->getReadAt());
    }

    public function testFluentInterface(): void
    {
        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $result = $notification
            ->setStatus(NotificationStatus::SENT->value)
            ->setTitle('New Title')
            ->setBody('New Body')
            ->setSentAt(new DateTimeImmutable());

        $this->assertSame($notification, $result);
    }

    public function testCreatedAtIsAutoSetInConstructor(): void
    {
        $beforeCreation = new DateTimeImmutable();

        $notification = new Notification(
            'user-123',
            NotificationTypes::INFO->value,
            'Title',
            'Body',
            NotificationServiceName::DIABETES->value
        );

        $afterCreation = new DateTimeImmutable();

        // CreatedAt should be set automatically and be between before and after
        $this->assertInstanceOf(\DateTimeInterface::class, $notification->getCreatedAt());
        $this->assertGreaterThanOrEqual($beforeCreation->getTimestamp(), $notification->getCreatedAt()->getTimestamp());
        $this->assertLessThanOrEqual($afterCreation->getTimestamp(), $notification->getCreatedAt()->getTimestamp());
    }
}
