<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Form\ShipmentView;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Control\Container;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Config\Source\LabelFormat;
use Polkurier\Shipping\Ui\Component\Control\OnClickScriptBuilder;
use Polkurier\Shipping\Ui\Component\Form\ShipmentView\LabelButton;

#[CoversClass(LabelButton::class)]
#[AllowMockObjectsWithoutExpectations]
class LabelButtonTest extends TestCase
{
    public function testThePlacedOrdersLabelPrintsAsAPdfOrFromTheListInAPrintersLanguage(): void
    {
        $data = $this->button(ShipmentInterface::STATE_CREATED)->getButtonData();

        self::assertSame(Container::SPLIT_BUTTON, $data['class_name']);
        self::assertSame('Print label', $data['label']);
        $defaults = \array_values(\array_filter($data['options'], static fn(array $option): bool => $option['default']));
        self::assertSame('PDF', $defaults[0]['label'], 'What the button itself prints.');
        self::assertStringContainsString('"polkurier\/shipment\/label?id=7"', $defaults[0]['onclick']);
        self::assertStringContainsString('format=EPL', $data['options'][3]['onclick']);
    }

    public function testAnOrderNotPlacedHasNoLabel(): void
    {
        self::assertSame([], $this->button(ShipmentInterface::STATE_FAILED)->getButtonData());
    }

    private function button(string $state): LabelButton
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturn('7');
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $path, array $params = []): string => $path . '?' . \http_build_query($params),
        );
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getId')->willReturn(7);
        $shipment->method('getState')->willReturn($state);
        $shipments = $this->createStub(ShipmentRepositoryInterface::class);
        $shipments->method('get')->willReturn($shipment);

        return new LabelButton(
            $request,
            $url,
            $this->createStub(AuthorizationInterface::class),
            $shipments,
            new OnClickScriptBuilder(new Json()),
            new LabelFormat(),
        );
    }
}
