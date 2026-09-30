<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\Carrier\AdditionalData;
use Polkurier\Webservice\Response\Carrier\AdditionalData\Pickup;

#[CoversClass(CarrierFeatures::class)]
class CarrierFeaturesTest extends TestCase
{
    public function testACarrierLeavingOutPartsOfItsAdditionalDataSupportsNoneOfThem(): void
    {
        $features = new CarrierFeatures();

        foreach ([new AdditionalData(), new AdditionalData(pickup: new Pickup())] as $data) {
            $carrier = new Carrier('DPD', 'DPD', false, $data);

            self::assertFalse($features->isCodAvailable($carrier));
            self::assertFalse($features->isInsuranceRequiredWithCod($carrier));
            self::assertFalse($features->isInsuranceAvailable($carrier));
            self::assertFalse($features->isPickupDateRequired($carrier));
            self::assertFalse($features->isPickupTimeRequired($carrier));
            self::assertFalse($features->isPickupDateSupported($carrier));
            self::assertFalse($features->isDropOffAvailable($carrier));
            self::assertFalse($features->isDropOffDefault($carrier));
            self::assertFalse($features->isRecipientPointAllowed($carrier));
        }
    }
}
