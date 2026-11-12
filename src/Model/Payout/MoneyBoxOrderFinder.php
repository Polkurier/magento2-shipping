<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Payout;

use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Webservice\Enum\Order\CodStatusEnum;

/**
 * The shop's Polkurier orders whose cash on delivery is in the money box, as of their last
 * status update. Polkurier pays out only the orders it can and drops the others without a word,
 * so a number typed in for a payout that isn't among them is worth a warning.
 */
readonly class MoneyBoxOrderFinder
{
    public function __construct(
        private ShipmentFinder $shipmentFinder,
    ) {}

    /**
     * @return string[]
     */
    public function findNumbers(): array
    {
        $numbers = [];
        foreach ($this->shipmentFinder->findWithCodStatus(CodStatusEnum::MoneyBox->value) as $shipment) {
            $numbers[] = (string)$shipment->getPolkurierNumber();
        }

        return \array_values(\array_unique(\array_filter($numbers)));
    }

    /**
     * Those of the given numbers not known here to be in the money box.
     *
     * @param string[] $numbers
     *
     * @return string[]
     */
    public function findUnknown(array $numbers): array
    {
        return \array_values(\array_diff($numbers, $this->findNumbers()));
    }
}
