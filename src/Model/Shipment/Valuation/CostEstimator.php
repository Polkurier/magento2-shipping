<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Valuation;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\Placement\CreateOrderMessageMapper;

/**
 * What Polkurier would charge for a draft, before it is placed. A price, not a dry run: a draft
 * that prices cleanly can still be refused when placed.
 */
readonly class CostEstimator
{
    public function __construct(
        private CreateOrderMessageMapper $createOrderMessageMapper,
        private ShipmentGateway $shipmentGateway,
    ) {}

    /**
     * What every carrier that can take the draft would charge for it. Carriers that can't, or
     * that the account can't use, are left out.
     *
     * @return array<string, array{net: float, gross: float}> by carrier code
     * @throws LocalizedException
     */
    public function quoteAll(ShipmentDraftInterface $draft, int $storeId): array
    {
        $quotes = [];
        $message = $this->createOrderMessageMapper->map($draft, $storeId);
        foreach ($this->shipmentGateway->valuate($message) as $valuation) {
            // A carrier that can't carry the shipment is quoted at 0.
            if ($valuation->isShipment() && $valuation->isAvailable()) {
                $quotes[$valuation->getServiceCode()] = [
                    'net' => $valuation->getNetPrice(),
                    'gross' => $valuation->getGrossPrice(),
                ];
            }
        }

        return $quotes;
    }
}
