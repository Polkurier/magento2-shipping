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
        $labels = $this->labels();

        self::assertSame('Outcome unknown', $labels->state(ShipmentInterface::STATE_UNKNOWN));
        self::assertSame('Pallet', $labels->shipmentType('palette'));
        self::assertSame('Non-standard', $labels->packType('NST'));
        self::assertSame('Next day', $labels->codType('1D'));
        self::assertSame('Polkurier money box', $labels->codReturnType('MB'));
        self::assertSame('Paid out', $labels->codStatus('PAID'));
        self::assertSame('SMS notification when dispatched', $labels->service('SMS_NOTIFICATION_RECIPIENT'));
    }

    public function testAServiceTheMerchantDoesntPickIsNamedAsWell(): void
    {
        // Left out of the list to pick from, as the cover address sets it; a shipment still has it.
        self::assertSame('Another sender on the label', $this->labels()->service('COVER_ADDRESS_SENDER'));
    }

    public function testACodeWithoutANameIsShownAsItIs(): void
    {
        $labels = $this->labels();

        self::assertSame('16D', $labels->codType('16D'));
        self::assertSame('EUR3', $labels->packType('EUR3'));
        self::assertSame('NEW_STATUS', $labels->codStatus('NEW_STATUS'));
    }

    public function testNoCodeIsShownAsADash(): void
    {
        $labels = $this->labels();

        self::assertSame('—', $labels->shipmentType(null));
        self::assertSame('—', $labels->codStatus(''));
        self::assertSame('—', $labels->state(''));
    }

    private function labels(): OptionLabelProvider
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
