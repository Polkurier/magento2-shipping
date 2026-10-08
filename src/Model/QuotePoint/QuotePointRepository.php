<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\QuotePoint;

use Polkurier\Shipping\Api\Data\QuotePointInterface;
use Polkurier\Shipping\Api\QuotePointRepositoryInterface;
use Polkurier\Shipping\Model\QuotePoint;
use Polkurier\Shipping\Model\QuotePointFactory;
use Polkurier\Shipping\Model\ResourceModel\QuotePoint as QuotePointResource;

/**
 * Stores the pickup point chosen at checkout.
 */
readonly class QuotePointRepository implements QuotePointRepositoryInterface
{
    public function __construct(
        private QuotePointFactory $quotePointFactory,
        private QuotePointResource $quotePointResource,
    ) {}

    public function getForQuote(int $quoteId, int $quoteAddressId = 0): ?QuotePointInterface
    {
        $point = $this->quotePointFactory->create();
        $this->quotePointResource->loadForQuote($point, $quoteId, $quoteAddressId);

        return $point->getPointId() !== '' ? $point : null;
    }

    public function save(QuotePointInterface $point): void
    {
        /** @var QuotePoint $point */
        $this->quotePointResource->save($point);
    }

    public function deleteForQuote(int $quoteId, int $quoteAddressId = 0): void
    {
        /** @var QuotePoint|null $point */
        $point = $this->getForQuote($quoteId, $quoteAddressId);
        if ($point !== null) {
            $this->quotePointResource->delete($point);
        }
    }
}
