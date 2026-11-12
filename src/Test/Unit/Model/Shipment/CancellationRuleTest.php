<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\CancellationRule;

#[CoversClass(CancellationRule::class)]
#[AllowMockObjectsWithoutExpectations]
class CancellationRuleTest extends TestCase
{
    /**
     * @return array<string, array{string, ?string, string, string[], bool, ?string}>
     */
    public static function provideOrders(): array
    {
        $placed = ShipmentInterface::STATE_CREATED;

        return [
            'awaiting the courier' => [$placed, 'OC', 'DPD', ['0001'], false, null],
            'confirmed' => [$placed, 'P', 'DPD', [], false, null],
            'not read yet' => [$placed, null, 'DPD', [], false, null],
            'with a reported problem' => [
                $placed, 'W', 'DPD', ['0001'], false,
                'Polkurier order 24680-27 is with the carrier, so it can\'t be cancelled.',
            ],
            'in transit' => [
                $placed, 'WP', 'DPD', ['0001'], false,
                'Polkurier order 24680-27 is with the carrier, so it can\'t be cancelled.',
            ],
            'delivered' => [
                $placed, 'D', 'DPD', ['0001'], false,
                'Polkurier order 24680-27 is with the carrier, so it can\'t be cancelled.',
            ],
            'on its way back' => [
                $placed, 'Z', 'DPD', ['0001'], false,
                'Polkurier order 24680-27 is with the carrier, so it can\'t be cancelled.',
            ],
            'invoiced' => [
                $placed, 'OC', 'DPD', ['0001'], true,
                'Polkurier order 24680-27 is invoiced, so it can\'t be cancelled.',
            ],
            'reported cancelled' => [
                $placed, 'A', 'DPD', [], false,
                'Polkurier reports order 24680-27 as cancelled.',
            ],
            'freight not confirmed' => [$placed, 'O', CancellationRule::FREIGHT_CARRIER, [], false, null],
            'freight confirmed' => [
                $placed, 'P', CancellationRule::FREIGHT_CARRIER, [], false,
                'Polkurier has confirmed freight order 24680-27, so it can\'t be cancelled.',
            ],
            'freight with a waybill' => [
                $placed, 'OC', CancellationRule::FREIGHT_CARRIER, ['0001'], false,
                'Polkurier has confirmed freight order 24680-27, so it can\'t be cancelled.',
            ],
            'cancelled' => [
                ShipmentInterface::STATE_CANCELLED, 'A', 'DPD', [], false,
                'Only a placed Polkurier order can be cancelled.',
            ],
        ];
    }

    /**
     * @param string[] $waybills
     */
    #[DataProvider('provideOrders')]
    public function testAnOrderIsOfferedForCancellingAsThePolkurierPanelOffersIt(
        string $state,
        ?string $status,
        string $carrier,
        array $waybills,
        bool $invoiced,
        ?string $refusal,
    ): void {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class);
        $shipment->setPolkurierNumber('24680-27')
            ->setState($state)
            ->setStatusCode($status)
            ->setCarrierCode($carrier)
            ->setWaybills($waybills)
            ->setDetails(['has_invoice' => $invoiced]);

        self::assertSame($refusal, CancellationRule::getRefusal($shipment)?->render());
    }
}
