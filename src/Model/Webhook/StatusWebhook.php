<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Webhook;

use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Psr\Log\LoggerInterface;

/**
 * What Polkurier's webhook says: that one of the account's orders was created, changed status or
 * was cancelled. The shipment placed with that order is refreshed as the scheduled updates do.
 *
 * Polkurier posts the event form-encoded: "type", "data" (the whole order, as get_orders gives
 * it) and "hash", an HMAC-SHA256 of the request's other fields, keyed with the secret the
 * Polkurier panel shows for the webhook. It sends each event once, in no set order, and without
 * a time or nonce to tell a repeat. So the order is read from Polkurier again rather than taken
 * from the event: a repeated, late or replayed event only refreshes what is current. Of the
 * event, only its order number is used.
 *
 * The signature is checked on the body as it came: "hash" is its last field, and what comes
 * before it is what was signed. Reading the fields first would depend on PHP's max_input_vars,
 * which a large order exceeds. That "hash" comes last is how Polkurier builds the event, not
 * something it documents: should that change, events are refused, never misread.
 */
readonly class StatusWebhook
{
    public const string RECEIVED = 'received';
    public const string REFUSED = 'refused';
    public const string FAILED = 'failed';

    private const string HASH_FIELD = 'hash';
    private const string NUMBER_FIELD = 'data[number]';

    public function __construct(
        private Config $config,
        private ShipmentFinder $finder,
        private ShipmentManagementInterface $management,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param string $body the request body, as posted
     *
     * @return string RECEIVED, REFUSED when it isn't signed with the secret (or there is none), or
     *     FAILED when the shipment couldn't be refreshed
     */
    public function receive(string $body): string
    {
        $secret = $this->config->getWebhookSecret();
        $signed = $this->signed($body);
        if ($secret === '' || $signed === null
            || !\hash_equals(\hash_hmac('sha256', $signed['payload'], $secret), \strtolower($signed['hash']))
        ) {
            return self::REFUSED;
        }
        $number = $this->field($signed['payload'], self::NUMBER_FIELD);
        if ($number === '') {
            return self::RECEIVED;
        }
        $failed = false;
        // Only a shipment of this shop's, placed and so known by its number; any other order of the
        // account, such as a copy of the shop's, or one placed in the panel, isn't this shop's to follow.
        foreach ($this->finder->byNumbers([$number], [ShipmentInterface::STATE_CREATED]) as $shipment) {
            $shipmentId = (int)$shipment->getId();
            try {
                $this->management->refreshStatus((int)$shipmentId);
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

    /**
     * @return array{payload: string, hash: string}|null the body without its last field, and that
     *     field's value, when that field is the hash
     */
    private function signed(string $body): ?array
    {
        $at = \strrpos($body, '&');
        if ($at === false) {
            return null;
        }
        $last = \explode('=', \substr($body, $at + 1), 2);
        if (\count($last) !== 2 || \urldecode($last[0]) !== self::HASH_FIELD) {
            return null;
        }

        return ['payload' => \substr($body, 0, $at), 'hash' => \urldecode($last[1])];
    }

    /**
     * One field of a form-encoded body, read without parsing the whole of it.
     */
    private function field(string $body, string $name): string
    {
        foreach (\explode('&', $body) as $pair) {
            $parts = \explode('=', $pair, 2);
            if (\urldecode($parts[0]) === $name) {
                return \trim(\urldecode($parts[1] ?? ''));
            }
        }

        return '';
    }
}
