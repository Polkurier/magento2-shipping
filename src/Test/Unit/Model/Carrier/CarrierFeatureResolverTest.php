<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\Carrier\AdditionalData;
use Polkurier\Webservice\Response\Carrier\AdditionalData\Pickup;
use Polkurier\Webservice\Response\Carrier\AdditionalData\Property;
use Polkurier\Webservice\Response\Carrier\AdditionalData\ShipmentType;

#[CoversClass(CarrierFeatureResolver::class)]
class CarrierFeatureResolverTest extends TestCase
{
    public function testACarrierLeavingOutPartsOfItsAdditionalDataSupportsNoneOfThem(): void
    {
        $features = new CarrierFeatureResolver();

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

    public function testACarrierTakingPalletsOffersTheServicesForPallets(): void
    {
        $features = new CarrierFeatureResolver();
        $types = static fn(bool $palette): ShipmentType => new ShipmentType(
            new Property(false, ''),
            new Property(true, ''),
            new Property($palette, ''),
        );

        $pallets = $features->getServices(
            new Carrier('AMBRO', 'Ambro', false, new AdditionalData(shipmentType: $types(true))),
        );
        $parcels = $features->getServices(
            new Carrier('INPOST', 'InPost', false, new AdditionalData(shipmentType: $types(false))),
        );

        foreach (CarrierFeatureResolver::PALLET_SERVICES as $service) {
            self::assertContains($service, $pallets);
            self::assertNotContains($service, $parcels);
        }
    }
}
