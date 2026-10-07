<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Enum\CacheTagEnum;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Webservice\Enum\Courier\CourierMessageSeverityEnum;
use Polkurier\Webservice\Enum\Courier\CourierMessageTypeEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Psr\Log\LoggerInterface;

/**
 * Polkurier's current notices about carriers (delays, suspensions, extra fees), shown on the
 * shipment screen next to the chosen carrier. Kept for an hour per account.
 */
readonly class CarrierNoticeProvider
{
    public const int CACHE_LIFETIME = 3600;

    /**
     * Markup kept in a notice's text; everything else is escaped.
     */
    private const array ALLOWED_TAGS = ['a', 'b', 'strong', 'em', 'i', 'br', 'p', 'span'];

    public function __construct(
        private PolkurierClientInterface $client,
        private PolkurierCache $cache,
        private Escaper $escaper,
        private LoggerInterface $logger,
        private Json $json,
        private AccountCache $accountCache,
    ) {}

    /**
     * @return array<int, array{carrier: string, html: string, severity: string, alert: bool}>
     */
    public function getNotices(): array
    {
        $key = $this->accountCache->key('carrier_notices');
        $cached = $this->cache->load($key);
        if (\is_string($cached) && $cached !== '') {
            try {
                $notices = $this->json->unserialize($cached);
            } catch (\InvalidArgumentException) {
                $notices = null;
            }
            if (\is_array($notices)) {
                return $notices;
            }
        }

        try {
            $messages = $this->client->system()->getCouriersMessages();
        } catch (PolkurierException $e) {
            // Notices are a courtesy: the screen works without them.
            $this->logger->warning('Polkurier carrier notices could not be read', ['exception' => $e]);

            return [];
        }
        $notices = [];
        foreach ($messages as $message) {
            $notices[] = [
                'carrier' => $message->getCourier(),
                'html' => $this->escaper->escapeHtml($message->getMessage(), self::ALLOWED_TAGS),
                'severity' => match ($message->getSeverity()) {
                    CourierMessageSeverityEnum::Success => 'success',
                    CourierMessageSeverityEnum::Danger => 'error',
                    default => 'warning',
                },
                'alert' => $message->getType() === CourierMessageTypeEnum::Alert,
            ];
        }
        $this->cache->save(
            $this->json->serialize($notices),
            $key,
            [CacheTagEnum::Notices->value],
            self::CACHE_LIFETIME,
        );

        return $notices;
    }
}
