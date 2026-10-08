<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Adminhtml;

use Magento\Framework\Message\Collection;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Message\MessageInterface;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Plugin\Adminhtml\ShipmentSavePlugin;

#[CoversClass(ShipmentSavePlugin::class)]
#[AllowMockObjectsWithoutExpectations]
class ShipmentSavePluginTest extends TestCase
{
    /** @var string[] the messages left, in order */
    private array $messages = [];

    public function testASaveWithoutAnUnreadyLabelKeepsMagentosMessage(): void
    {
        self::assertSame('saved', $this->save(null));
        self::assertSame(['The shipment has been created.'], $this->messages);
    }

    public function testAnUnreadyLabelReplacesMagentosMessage(): void
    {
        self::assertSame('saved', $this->save('PK123'));
        self::assertCount(2, $this->messages);
        self::assertSame('Other', $this->messages[0]);
        self::assertStringStartsWith('The shipment has been created with Polkurier order PK123', $this->messages[1]);
    }

    private function save(?string $unready): mixed
    {
        $this->messages = $unready === null
            ? ['The shipment has been created.']
            : ['The shipment has been created. You created the shipping label.', 'Other'];
        $taken = [];
        foreach ($this->messages as $text) {
            $message = $this->createStub(MessageInterface::class);
            $message->method('getText')->willReturn($text);
            $taken[] = $message;
        }
        $collection = $this->createStub(Collection::class);
        $collection->method('getItems')->willReturnCallback(function () use ($taken): array {
            $this->messages = [];

            return $taken;
        });
        $manager = $this->createStub(ManagerInterface::class);
        $manager->method('getMessages')->willReturn($collection);
        $manager->method('addMessage')->willReturnCallback(function (MessageInterface $message) use ($manager) {
            $this->messages[] = (string)$message->getText();

            return $manager;
        });
        $manager->method('addNoticeMessage')->willReturnCallback(function ($message) use ($manager) {
            $this->messages[] = (string)$message;

            return $manager;
        });
        $labels = $this->createStub(NativeLabelService::class);
        $labels->method('takeUnreadyLabel')->willReturn($unready);

        return (new ShipmentSavePlugin($manager, $labels))->afterExecute($this->createStub(Save::class), 'saved');
    }
}
