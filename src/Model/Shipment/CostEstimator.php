<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Mapper\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\Sdk\ShipmentGatewayInterface;
use Psr\Log\LoggerInterface;

/**
 * What Polkurier would charge for a draft, before it is placed. A price, not a dry run: a draft
 * that prices cleanly can still be refused when placed.
 *
 * The prices are read by ValuationQuotes, whichever of the shapes Polkurier answers with.
 */
readonly class CostEstimator
{
    public function __construct(
        private CreateOrderMessageMapper $messageMapper,
        private ShipmentGatewayInterface $gateway,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array{net: float, gross: float}|null null when the carrier did not price the draft
     * @throws LocalizedException
     */
    public function estimate(ShipmentDraftInterface $draft, int $storeId): ?array
    {
        return $this->quoteAll($draft, $storeId)[$draft->getCarrierCode()] ?? null;
    }

    /**
     * What every carrier that can take the draft would charge for it. Carriers that can't are
     * left out.
     *
     * @return array<string, array{net: float, gross: float}> by carrier code
     * @throws LocalizedException
     */
    public function quoteAll(ShipmentDraftInterface $draft, int $storeId): array
    {
        $message = $this->messageMapper->map($draft, $storeId);
        $valuation = $this->gateway->valuate($message);

        ['quotes' => $quotes, 'unrecognised' => $unrecognised] = ValuationQuotes::fromResponse($valuation, $message->getCourier());
        if ($unrecognised > 0) {
            $this->logger->warning('Polkurier valuation entries not understood', ['count' => $unrecognised, 'response' => $valuation]);
        }

        return $quotes;
    }
}
