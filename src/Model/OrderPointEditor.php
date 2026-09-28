<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Changes the pickup point of an order after it was placed, as the merchant does from the order
 * page when a customer asks for another point. The point is checked with Polkurier for the
 * order's carrier first, as at checkout, and the change is noted on the order.
 */
class OrderPointEditor
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly OrderPointStore $pointStore,
        private readonly CarrierCatalog $catalog,
        private readonly CarrierFeatures $features,
        private readonly ErrorMessages $errorMessages,
    ) {}

    /**
     * @param string|null $pointId empty to remove the point, where the carrier doesn't need one
     * @return OrderPointInterface|null the point now stored
     * @throws LocalizedException
     */
    public function change(int $orderId, ?string $pointId): ?OrderPointInterface
    {
        $order = $this->orderRepository->get($orderId);
        if (!$order instanceof Order) {
            throw new LocalizedException(\__('The order no longer exists.'));
        }
        $methodCode = Polkurier::methodCodeOf((string)$order->getShippingMethod());
        if ($methodCode === null) {
            throw new LocalizedException(\__('Only orders placed with a Polkurier method have a pickup point.'));
        }
        $storeId = (int)$order->getStoreId();
        try {
            $carrier = $this->catalog->getCarrier($methodCode, $storeId);
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }
        if ($carrier === null || !$this->features->isRecipientPointAllowed($carrier)) {
            throw new LocalizedException(\__('This carrier doesn\'t deliver to pickup points.'));
        }

        $before = $this->pointStore->getForOrder($orderId);
        $pointId = \trim((string)$pointId);
        $previousId = $before?->getPointId();
        if ($pointId === '') {
            if ($this->features->isRecipientPointRequired($carrier)) {
                throw new LocalizedException(\__('This carrier delivers to pickup points only: enter one.'));
            }
            $this->pointStore->deleteForOrder($orderId);
            $this->comment($order, $previousId, null);

            return null;
        }

        try {
            $found = $this->catalog->findPoint($pointId, $this->features->getPointProviders($carrier), $storeId);
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }
        if ($found === null) {
            throw new LocalizedException(\__('There is no pickup point %1 for this carrier.', $pointId));
        }

        $point = $before ?? $this->pointStore->createForOrder($orderId);
        $point->setMethodCode($methodCode)
            ->setPointId($pointId)
            ->setPointLabel(\mb_substr($found->getAddress() ?: \trim($found->getStreet() . ' ' . $found->getZip() . ' ' . $found->getCity()), 0, 512))
            ->setPointCod($found->isCod());
        $this->pointStore->saveForOrder($point);
        $this->comment($order, $previousId, $pointId);

        return $point;
    }

    private function comment(Order $order, ?string $before, ?string $after): void
    {
        if ($before === $after) {
            return;
        }
        $order->addCommentToStatusHistory((string)\__(
            'Pickup point changed from %1 to %2.',
            $before ?? (string)\__('none'),
            $after ?? (string)\__('none'),
        ));
        $this->orderRepository->save($order);
    }
}
