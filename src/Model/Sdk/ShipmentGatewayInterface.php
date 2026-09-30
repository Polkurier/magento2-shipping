<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Webservice\Request\Courier\GetPickupTimeMessage;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Request\Order\GetDocumentMessage;
use Polkurier\Webservice\Request\Order\GetOrdersMessage;
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
 * The Polkurier calls the shipment side of the module makes, in the module's terms. Requests and
 * answers are the SDK's messages, but no SDK exception gets past: a refusal or failure is a LocalizedException describing it for the
 * merchant, and placing an order that may have gone through anyway is a
 * PlacementUncertainException.
 */
interface ShipmentGatewayInterface
{
    /**
     * @throws PlacementUncertainException when the order may have been placed nonetheless
     * @throws LocalizedException when it certainly wasn't
     */
    public function placeOrder(CreateOrderMessage $message): CreatedOrderMessage;

    /**
     * @throws LocalizedException
     */
    public function cancelOrder(OrderIdMessage $message): CancellationMessage;

    /**
     * @throws LocalizedException
     */
    public function findOrders(GetOrdersMessage $message): OrderListMessage;

    /**
     * @throws LocalizedException
     */
    public function getStatus(string $number, bool $tracking): OrderStatusMessage;

    /**
     * @throws LocalizedException
     */
    public function getLabel(GetDocumentMessage $message): DocumentMessage;

    /**
     * @throws LocalizedException
     */
    public function getProtocol(GetDocumentMessage $message): DocumentMessage;

    /**
     * What each carrier would charge for the order, as the API answers.
     *
     * @return array<mixed>
     * @throws LocalizedException
     */
    public function valuate(CreateOrderMessage $message): array;

    /**
     * @return PickupTimeMessage[]
     * @throws LocalizedException
     */
    public function getPickupTimes(GetPickupTimeMessage $message): array;

    /**
     * @throws LocalizedException
     */
    public function isMultiPickupAvailable(): bool;

    /**
     * @throws LocalizedException
     */
    public function bookMultiPickup(MultiPickupRequestMessage $message): MultiPickupResultMessage;
}
