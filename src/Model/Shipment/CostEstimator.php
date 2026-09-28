<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Mapper\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * What Polkurier would charge for a draft, before it is placed. A price, not a dry run: a draft
 * that prices cleanly can still be refused when placed.
 *
 * The quote comes as a list, one per carrier, or as a single quote for the draft's carrier; both
 * are read.
 */
class CostEstimator
{
    public function __construct(
        private readonly CreateOrderMessageMapper $messageMapper,
        private readonly ClientPool $clientPool,
        private readonly ErrorMessages $errorMessages,
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
        try {
            $valuation = $this->clientPool->orders($storeId)->orderValuation($message);
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }

        $quotes = [];
        foreach (\array_is_list($valuation) ? $valuation : [$valuation] as $quote) {
            if (!\is_array($quote) || !isset($quote['netprice'], $quote['grossprice']) || ($quote['available'] ?? true) === false) {
                continue;
            }
            $code = (string)($quote['servicecode'] ?? $message->getCourier());
            $quotes[$code] = ['net' => (float)$quote['netprice'], 'gross' => (float)$quote['grossprice']];
        }

        return $quotes;
    }
}
