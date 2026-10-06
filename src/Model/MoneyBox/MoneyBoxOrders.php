<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\MoneyBox;

use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Webservice\Enum\Order\CodStatusEnum;

/**
 * The shop's Polkurier orders whose cash on delivery is in the money box, as of their last
 * status update. Polkurier pays out only the orders it can and drops the others without a word,
 * so a number typed in for a payout that isn't among them is worth a warning.
 */
readonly class MoneyBoxOrders
{
    public function __construct(
        private ShipmentFinder $finder,
    ) {}

    /**
     * @return string[]
     */
    public function numbers(): array
    {
        $numbers = [];
        foreach ($this->finder->withCodStatus(CodStatusEnum::MoneyBox->value) as $shipment) {
            $numbers[] = (string)$shipment->getPolkurierNumber();
        }

        return \array_values(\array_unique(\array_filter($numbers)));
    }

    /**
     * Those of the numbers not known here as in the money box.
     *
     * @param string[] $numbers
     *
     * @return string[]
     */
    public function unknown(array $numbers): array
    {
        return \array_values(\array_diff($numbers, $this->numbers()));
    }
}
