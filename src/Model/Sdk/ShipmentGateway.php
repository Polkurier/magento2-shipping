<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Polkurier\Shipping\Model\ErrorMessages;
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
use Polkurier\Webservice\Request\Order\MultiPickupRequestMessage;
use Polkurier\Webservice\Request\Order\OrderIdMessage;
use Polkurier\Webservice\Response\Order\CancellationMessage;
use Polkurier\Webservice\Response\Order\CreatedOrderMessage;
use Polkurier\Webservice\Response\Order\DocumentMessage;
use Polkurier\Webservice\Response\Order\MultiPickupResultMessage;
use Polkurier\Webservice\Response\Order\OrderListMessage;
use Polkurier\Webservice\Response\Order\OrderStatusMessage;

/**
 * The shipment calls over the SDK clients of the client pool.
 */
class ShipmentGateway implements ShipmentGatewayInterface
{
    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly ErrorMessages $errorMessages,
    ) {}

    public function placeOrder(CreateOrderMessage $message): CreatedOrderMessage
    {
        try {
            return $this->clientPool->orders()->createOrder($message);
        } catch (PolkurierException $e) {
            if ($this->mayHaveBeenPlaced($e)) {
                throw new PlacementUncertainException(new Phrase($this->errorMessages->describe($e)), $e);
            }

            throw $this->errorMessages->exception($e);
        }
    }

    public function cancelOrder(OrderIdMessage $message): CancellationMessage
    {
        return $this->call(fn(): CancellationMessage => $this->clientPool->orders()->cancelOrder($message));
    }

    public function findOrders(GetOrdersMessage $message): OrderListMessage
    {
        return $this->call(fn(): OrderListMessage => $this->clientPool->orders()->getOrders($message));
    }

    public function getStatus(string $number, bool $tracking): OrderStatusMessage
    {
        return $this->call(fn(): OrderStatusMessage => $this->clientPool->orders()->getStatus(new GetStatusMessage($number, tracking: $tracking)));
    }

    public function getLabel(GetDocumentMessage $message): DocumentMessage
    {
        return $this->call(fn(): DocumentMessage => $this->clientPool->orders()->getLabel($message));
    }

    public function getProtocol(GetDocumentMessage $message): DocumentMessage
    {
        return $this->call(fn(): DocumentMessage => $this->clientPool->orders()->getProtocol($message));
    }

    public function valuate(CreateOrderMessage $message): array
    {
        return $this->call(fn(): array => $this->clientPool->orders()->orderValuation($message));
    }

    public function getPickupTimes(GetPickupTimeMessage $message): array
    {
        return $this->call(fn(): array => $this->clientPool->courier()->getCourierPickupTime($message));
    }

    public function isMultiPickupAvailable(): bool
    {
        return $this->call(fn(): bool => $this->clientPool->orders()->isMultiPickupAvailable()->isAvailable());
    }

    public function bookMultiPickup(MultiPickupRequestMessage $message): MultiPickupResultMessage
    {
        return $this->call(fn(): MultiPickupResultMessage => $this->clientPool->orders()->createMultiOrderPickup($message));
    }

    /**
     * @template T
     * @param callable(): T $request
     * @return T
     * @throws LocalizedException
     */
    private function call(callable $request): mixed
    {
        try {
            return $request();
        } catch (PolkurierException $e) {
            throw $this->errorMessages->exception($e);
        }
    }

    /**
     * Placing an order isn't idempotent, and only a refusal with a specific reason (validation,
     * carrier, account) is known to have created nothing. No answer, one that couldn't be read,
     * or a vaguer refusal may hide a placed order.
     */
    private function mayHaveBeenPlaced(PolkurierException $e): bool
    {
        if ($e instanceof ConnectionException || $e instanceof MalformedResponseException) {
            return true;
        }
        if (!$e instanceof WebserviceException) {
            return false;
        }
        $code = $e->getErrorMessage()->getErrorCode();

        return $code === null || $code === '' || $code === ApiErrorCodeEnum::UnknownError->value;
    }
}
