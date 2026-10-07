<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Valuation;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\Placement\CreateOrderMessageMapper;
use Psr\Log\LoggerInterface;

/**
 * What Polkurier would charge for a draft, before it is placed. A price, not a dry run: a draft
 * that prices cleanly can still be refused when placed.
 *
 * The prices are read whichever of the shapes Polkurier answers with (readQuotes()).
 */
readonly class CostEstimator
{
    public function __construct(
        private CreateOrderMessageMapper $messageMapper,
        private ShipmentGateway $gateway,
        private LoggerInterface $logger,
    ) {}

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

        ['quotes' => $quotes, 'unrecognised' => $unrecognised] = self::readQuotes($valuation, $message->getCourier());
        if ($unrecognised > 0) {
            $this->logger->warning(
                'Polkurier valuation entries not understood',
                ['count' => $unrecognised, 'response' => $valuation],
            );
        }

        return $quotes;
    }

    /**
     * @param array<array-key, mixed> $valuation
     * @param string $carrierCode the draft's carrier, for a quote that doesn't name one
     *
     * @return array{quotes: array<string, array{net: float, gross: float}>, unrecognised: int}
     */
    private static function readQuotes(array $valuation, string $carrierCode): array
    {
        $entries = [];
        if (self::price($valuation) !== null) {
            $entries[] = [$carrierCode, $valuation];
        } else {
            foreach ($valuation as $key => $entry) {
                $code = \is_array($entry) && isset($entry['servicecode'])
                    ? (string)$entry['servicecode']
                    : (\is_string($key) ? $key : null);
                $entries[] = [$code, $entry];
            }
        }

        $quotes = [];
        $unrecognised = 0;
        foreach ($entries as [$code, $entry]) {
            $price = \is_array($entry) ? self::price($entry) : null;
            if ($code === null || $code === '' || $price === null) {
                // An entry without prices is a carrier that can't price the draft; one we can't
                // read at all is what the caller should hear about.
                $unrecognised += \is_array($entry) && isset($entry['servicecode']) ? 0 : 1;
                continue;
            }
            if (($entry['available'] ?? true) !== false) {
                $quotes[$code] = $price;
            }
        }

        return ['quotes' => $quotes, 'unrecognised' => $unrecognised];
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @return array{net: float, gross: float}|null
     */
    private static function price(array $entry): ?array
    {
        foreach ([['netprice', 'grossprice'], ['price_net', 'price_gross']] as [$net, $gross]) {
            if (isset($entry[$net], $entry[$gross]) && \is_numeric($entry[$net]) && \is_numeric($entry[$gross])) {
                return ['net' => (float)$entry[$net], 'gross' => (float)$entry[$gross]];
            }
        }

        return null;
    }
}
