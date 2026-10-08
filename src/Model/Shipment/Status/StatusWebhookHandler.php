<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Status;

use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Webservice\Exception\InvalidArgumentException;
use Polkurier\Webservice\Webhook\OrderWebhookReader;

/**
 * Handles Polkurier's webhook, which says that one of the account's orders was created, changed
 * status or was cancelled. The shipment placed with that order is refreshed as the scheduled
 * updates do.
 *
 * The SDK's {@see OrderWebhookReader} checks the event against the secret the Polkurier panel shows
 * for the webhook, and reads it. Polkurier sends each event once, in no set order, and without a
 * time or nonce to tell a repeat. So the order is read from Polkurier again rather than taken from
 * the event: a repeated, late or replayed event only refreshes what is current. Only the event's
 * order number is used.
 */
readonly class StatusWebhookHandler
{
    public const string RECEIVED = 'received';
    public const string REFUSED = 'refused';
    public const string FAILED = 'failed';

    public function __construct(
        private ConfigProvider $configProvider,
        private ShipmentFinder $shipmentFinder,
        private ShipmentManagementInterface $shipmentManagement,
        private OrderWebhookReader $orderWebhookReader,
        private Logger $logger,
    ) {}

    /**
     * @param string $body the request body, as posted
     *
     * @return string RECEIVED, REFUSED when it isn't signed with the secret (or there is none), or
     *     FAILED when the shipment couldn't be refreshed
     */
    public function receive(string $body): string
    {
        $secret = $this->configProvider->getWebhookSecret();
        if ($secret === '' || !$this->orderWebhookReader->isAuthentic($body, $secret)) {
            return self::REFUSED;
        }
        try {
            $number = \trim((string)$this->orderWebhookReader->read($body)->getOrder()->getNumber());
        } catch (InvalidArgumentException $e) {
            // Signed, so Polkurier's, but not an order this module can read: nothing to follow.
            $this->logger->warning('Polkurier status webhook: the event was not read', ['exception' => $e]);

            return self::RECEIVED;
        }
        if ($number === '') {
            return self::RECEIVED;
        }
        $failed = false;
        // Only a placed shipment of this shop, which is known by its number. Other orders of the
        // account, such as a copy of the shop's or one placed in the panel, aren't this shop's to
        // follow.
        foreach ($this->shipmentFinder->byNumbers([$number], [ShipmentInterface::STATE_CREATED]) as $shipment) {
            $shipmentId = (int)$shipment->getId();
            try {
                $this->shipmentManagement->refreshStatus($shipmentId);
            } catch (\Throwable $e) {
                $failed = true;
                // The scheduled updates still follow it.
                $this->logger->warning('Polkurier status webhook: the shipment was not refreshed', [
                    'shipment' => $shipmentId,
                    'exception' => $e,
                ]);
            }
        }

        return $failed ? self::FAILED : self::RECEIVED;
    }
}
