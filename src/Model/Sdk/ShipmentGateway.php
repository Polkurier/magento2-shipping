<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Exception\PlacementUncertainException;
use Polkurier\Webservice\Enum\Http\ApiErrorCodeEnum;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Exception\MalformedResponseException;
use Polkurier\Webservice\Exception\WebserviceException;
use Polkurier\Webservice\Request\Courier\GetPickupTimeMessage;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Request\Order\GetDocumentMessage;
use Polkurier\Webservice\Request\Order\GetOrdersMessage;
use Polkurier\Webservice\Request\Order\GetStatusMessage;
use Polkurier\Webservice\Request\Order\MultiPickupAvailabilityRequestMessage;
use Polkurier\Webservice\Request\Order\MultiPickupRequestMessage;
use Polkurier\Webservice\Request\Order\OrderIdMessage;
use Polkurier\Webservice\Response\Courier\PickupTimeMessage;
use Polkurier\Webservice\Response\Order\CancellationMessage;
use Polkurier\Webservice\Response\Order\CreatedOrderMessage;
use Polkurier\Webservice\Response\Order\DocumentMessage;
use Polkurier\Webservice\Response\Order\MultiPickupResultMessage;
use Polkurier\Webservice\Response\Order\OrderListMessage;
use Polkurier\Webservice\Response\Order\OrderStatusMessage;

/**
 * The Polkurier calls the shipment side of the module makes, over PolkurierClient, in the module's
 * terms. Requests and answers are the SDK's messages, but no SDK exception gets past: a refusal or
 * failure is a LocalizedException describing it for the merchant, and placing an order that may
 * have gone through anyway is a PlacementUncertainException.
 */
readonly class ShipmentGateway
{
    /**
     * The one refusal without an error code that Polkurier gives after an order may already be
     * saved: a failure while saving it, or right after. Every other uncoded refusal (validation,
     * the carrier's checks, prices, funds, collective pickup) comes before anything is saved.
     */
    private const string FATAL_PREFIX = 'Wystąpił nieoczekiwany błąd';

    /**
     * cURL errors that mean the request never reached Polkurier: no proxy or host found, no
     * connection, no TLS handshake.
     */
    private const array NOT_SENT_CURL_ERRORS = [5, 6, 7, 35];

    public function __construct(
        private PolkurierClientInterface $client,
        private ErrorMessageResolver $errorMessageResolver,
    ) {}

    /**
     * @throws PlacementUncertainException when the order may have been placed nonetheless
     * @throws LocalizedException when it certainly wasn't
     */
    public function placeOrder(CreateOrderMessage $message): CreatedOrderMessage
    {
        try {
            return $this->client->orders()->createOrder($message);
        } catch (PolkurierException $e) {
            if ($this->mayHaveBeenPlaced($e)) {
                throw new PlacementUncertainException($this->errorMessageResolver->phrase($e), $e);
            }

            throw $this->errorMessageResolver->exception($e);
        }
    }

    /**
     * @throws LocalizedException
     */
    public function cancelOrder(OrderIdMessage $message): CancellationMessage
    {
        return $this->call(fn(): CancellationMessage => $this->client->orders()->cancelOrder($message));
    }

    /**
     * @throws LocalizedException
     */
    public function findOrders(GetOrdersMessage $message): OrderListMessage
    {
        return $this->call(fn(): OrderListMessage => $this->client->orders()->getOrders($message));
    }

    /**
     * @throws LocalizedException
     */
    public function getStatus(string $number, bool $tracking): OrderStatusMessage
    {
        return $this->call(fn(): OrderStatusMessage => $this->client->orders()->getStatus(
            new GetStatusMessage($number, tracking: $tracking),
        ));
    }

    /**
     * @throws LocalizedException
     */
    public function getLabel(GetDocumentMessage $message): DocumentMessage
    {
        return $this->call(fn(): DocumentMessage => $this->client->orders()->getLabel($message));
    }

    /**
     * @throws LocalizedException
     */
    public function getProtocol(GetDocumentMessage $message): DocumentMessage
    {
        return $this->call(fn(): DocumentMessage => $this->client->orders()->getProtocol($message));
    }

    /**
     * What each carrier would charge for the order, as the API answers.
     *
     * @return array<mixed>
     * @throws LocalizedException
     */
    public function valuate(CreateOrderMessage $message): array
    {
        return $this->call(fn(): array => $this->client->orders()->orderValuation($message));
    }

    /**
     * @return PickupTimeMessage[]
     * @throws LocalizedException
     */
    public function getPickupTimes(GetPickupTimeMessage $message): array
    {
        return $this->call(fn(): array => $this->client->courier()->getCourierPickupTime($message));
    }

    /**
     * Whether a shipment can be collected with others, as Polkurier answers for its carrier and
     * parcels: false also when it can't tell.
     *
     * @throws LocalizedException
     */
    public function isMultiPickupAvailable(MultiPickupAvailabilityRequestMessage $message): bool
    {
        return $this->call(fn(): bool => $this->client->orders()->isMultiPickupAvailable($message)->isAvailable());
    }

    /**
     * @throws LocalizedException
     */
    public function bookMultiPickup(MultiPickupRequestMessage $message): MultiPickupResultMessage
    {
        return $this->call(fn(): MultiPickupResultMessage => $this->client->orders()->createMultiOrderPickup($message));
    }

    /**
     * @template T
     *
     * @param callable(): T $request
     *
     * @return T
     * @throws LocalizedException
     */
    private function call(callable $request): mixed
    {
        try {
            return $request();
        } catch (PolkurierException $e) {
            throw $this->errorMessageResolver->exception($e);
        }
    }

    /**
     * Placing an order isn't idempotent, so an order is taken as possibly placed unless the
     * failure says it can't have been: a request that never left, or a refusal with a reason that
     * Polkurier only gives before saving anything. An answer lost or unreadable, UNKNOWN_ERROR, the
     * fatal refusal (FATAL_PREFIX) and a refusal with no reason at all may hide a placed order.
     */
    private function mayHaveBeenPlaced(PolkurierException $e): bool
    {
        if ($e instanceof ConnectionException) {
            return !$this->wasNotSent($e);
        }
        if ($e instanceof MalformedResponseException) {
            return true;
        }
        if (!$e instanceof WebserviceException) {
            return false;
        }
        $error = $e->getErrorMessage();
        $code = (string)$error->getErrorCode();
        if ($code === ApiErrorCodeEnum::UnknownError->value) {
            return true;
        }
        if ($code !== '') {
            return false;
        }
        $reason = \trim(\is_string($error->getResponse()) ? $error->getResponse() : '');

        return $reason === '' || \str_starts_with($reason, self::FATAL_PREFIX);
    }

    /**
     * Whether the connection failed before the request went out: so Polkurier can't have it. A
     * timeout counts only while connecting; one while waiting for the answer may follow a placed order.
     */
    private function wasNotSent(ConnectionException $e): bool
    {
        $message = $e->getMessage();
        $previous = $e->getPrevious();
        $errno = $previous !== null && \method_exists($previous, 'getHandlerContext')
            ? (int)($previous->getHandlerContext()['errno'] ?? 0)
            : 0;
        if ($errno === 0 && \preg_match('/cURL error (\d+):/', $message, $match)) {
            $errno = (int)$match[1];
        }

        return \in_array($errno, self::NOT_SENT_CURL_ERRORS, true)
            || ($errno === 28 && (
                \str_contains($message, 'Connection timed out')
                || \str_contains($message, 'Resolving timed out')
            ));
    }
}
