<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Details;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Config\Source\AdditionalService;
use Polkurier\Shipping\Model\Config\Source\CodReturnType;
use Polkurier\Shipping\Model\Config\Source\CodStatus;
use Polkurier\Shipping\Model\Config\Source\CodType;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\Config\Source\ShipmentState;
use Polkurier\Shipping\Model\Config\Source\ShipmentType;
use Polkurier\Shipping\Model\Shipment\Details\OptionLabelProvider;

#[CoversClass(OptionLabelProvider::class)]
class OptionLabelProviderTest extends TestCase
{
    public function testCodesAreNamedFromTheModulesOptionLists(): void
    {
        $labels = $this->createProvider();

        self::assertSame('Outcome unknown', $labels->getStateLabel(ShipmentInterface::STATE_UNKNOWN));
        self::assertSame('Pallet', $labels->getShipmentTypeLabel('palette'));
        self::assertSame('Non-standard', $labels->getPackTypeLabel('NST'));
        self::assertSame('Next day', $labels->getCodTypeLabel('1D'));
        self::assertSame('Polkurier money box', $labels->getCodReturnTypeLabel('MB'));
        self::assertSame('Paid out', $labels->getCodStatusLabel('PAID'));
        self::assertSame('SMS notification when dispatched', $labels->getServiceLabel('SMS_NOTIFICATION_RECIPIENT'));
    }

    public function testAServiceTheMerchantDoesntPickIsNamedAsWell(): void
    {
        // Left out of the list to pick from, as the cover address sets it; a shipment still has it.
        self::assertSame(
            'Another sender on the label',
            $this->createProvider()->getServiceLabel('COVER_ADDRESS_SENDER'),
        );
    }

    public function testACodeWithoutANameIsShownAsItIs(): void
    {
        $labels = $this->createProvider();

        self::assertSame('16D', $labels->getCodTypeLabel('16D'));
        self::assertSame('EUR3', $labels->getPackTypeLabel('EUR3'));
        self::assertSame('NEW_STATUS', $labels->getCodStatusLabel('NEW_STATUS'));
    }

    public function testNoCodeIsShownAsADash(): void
    {
        $labels = $this->createProvider();

        self::assertSame('—', $labels->getShipmentTypeLabel(null));
        self::assertSame('—', $labels->getCodStatusLabel(''));
        self::assertSame('—', $labels->getStateLabel(''));
    }

    private function createProvider(): OptionLabelProvider
    {
        return new OptionLabelProvider(
            new ShipmentState(),
            new ShipmentType(),
            new PackType(),
            new CodType(),
            new CodReturnType(),
            new CodStatus(),
            new AdditionalService(),
        );
    }
}
