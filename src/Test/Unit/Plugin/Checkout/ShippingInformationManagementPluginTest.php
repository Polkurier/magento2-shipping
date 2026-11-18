<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Checkout;

use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Api\ShippingInformationManagementInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\PickupPoint\PointSelectionRecorder;
use Polkurier\Shipping\Plugin\Checkout\ShippingInformationManagementPlugin;
use Polkurier\Shipping\Test\Unit\Support\ShippingInformationExtensionWithPoint;

#[CoversClass(ShippingInformationManagementPlugin::class)]
#[AllowMockObjectsWithoutExpectations]
class ShippingInformationManagementPluginTest extends TestCase
{
    public function testThePointChosenInCheckoutIsRecordedWithTheMethod(): void
    {
        $quote = $this->createStub(CartInterface::class);
        $quotes = $this->createMock(CartRepositoryInterface::class);
        $quotes->expects(self::once())->method('getActive')->with(15)->willReturn($quote);
        $extension = $this->createStub(ShippingInformationExtensionWithPoint::class);
        $extension->method('getPickupPointId')->willReturn('KRA01M');
        $information = $this->createInformation($extension);
        $recorder = $this->createMock(PointSelectionRecorder::class);
        $recorder->expects(self::once())->method('select')->with($quote, 'polkurier', 'INPOST', 'KRA01M');

        $arguments = (new ShippingInformationManagementPlugin($quotes, $recorder))->beforeSaveAddressInformation(
            $this->createStub(ShippingInformationManagementInterface::class),
            '15',
            $information,
        );

        self::assertSame([15, $information], $arguments);
    }

    public function testWithoutExtensionAttributesNoPointIsRecorded(): void
    {
        $quotes = $this->createStub(CartRepositoryInterface::class);
        $quotes->method('getActive')->willReturn($this->createStub(CartInterface::class));
        $recorder = $this->createMock(PointSelectionRecorder::class);
        $recorder->expects(self::once())->method('select')->with(self::anything(), 'polkurier', 'INPOST', null);

        (new ShippingInformationManagementPlugin($quotes, $recorder))->beforeSaveAddressInformation(
            $this->createStub(ShippingInformationManagementInterface::class),
            15,
            $this->createInformation(null),
        );
    }

    private function createInformation(?ShippingInformationExtensionWithPoint $extension): ShippingInformationInterface
    {
        $information = $this->createStub(ShippingInformationInterface::class);
        $information->method('getShippingCarrierCode')->willReturn('polkurier');
        $information->method('getShippingMethodCode')->willReturn('INPOST');
        $information->method('getExtensionAttributes')->willReturn($extension);

        return $information;
    }
}
