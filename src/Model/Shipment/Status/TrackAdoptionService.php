<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;
use Magento\Sales\Model\Order as MagentoOrder;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Shipment\Native\NativeFollowUpService;
use Polkurier\Shipping\Model\Shipment\Native\NativeShipmentService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\OrderLock;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\ShipmentFactory;
use Polkurier\Webservice\Response\Order;

/**
 * Links the Polkurier orders of tracking numbers added to a Magento shipment from elsewhere, e.g.
 * the waybills an import of the shop's orders into Polkurier sends back, whatever the order's
 * shipping method. A number is looked up on the shop's Polkurier account; an order found there,
 * and made for the Magento order ({@see RemoteOrderMatcher::isMadeFor()}), becomes the order's
 * Polkurier shipment, as if placed here: its tracks become the module's, its other waybills are
 * added, the order's shipping follows it, and its status is kept up to date. A number not found, or
 * found on an order made for another, stays a plain track.
 *
 * The Polkurier order goes with the Magento shipment the number was added to, unless another live
 * Polkurier order holds that one: it then goes without one, its track staying where it was added.
 *
 * Numbers the module's shipments hold are skipped, so adding one again changes nothing. Failures
 * are logged: the track stands either way.
 */
readonly class TrackAdoptionService
{
    public function __construct(
        private RemoteOrderMatcher $remoteOrderMatcher,
        private RemoteOrderApplier $remoteOrderApplier,
        private ShipmentFinder $shipmentFinder,
        private ShipmentFactory $shipmentFactory,
        private ShipmentRepositoryInterface $shipmentRepository,
        private MagentoShipmentRepository $magentoShipmentRepository,
        private OrderLoader $orderLoader,
        private OrderLock $orderLock,
        private NativeShipmentService $nativeShipmentService,
        private NativeFollowUpService $nativeFollowUpService,
        private StatusRefreshService $statusRefreshService,
        private Logger $logger,
    ) {}

    /**
     * @param string[] $numbers the tracking numbers added to the Magento shipment
     */
    public function adopt(int $magentoShipmentId, array $numbers): void
    {
        $numbers = \array_unique(\array_filter(\array_map(
            static fn($number): string => \trim((string)$number),
            $numbers,
        )));
        foreach ($numbers as $number) {
            try {
                $this->adoptNumber($magentoShipmentId, $number);
            } catch (\Throwable $e) {
                $this->logger->warning('A tracking number was not linked to its Polkurier order', [
                    'shipment_id' => $magentoShipmentId,
                    'number' => $number,
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * @throws LocalizedException
     */
    private function adoptNumber(int $magentoShipmentId, string $number): void
    {
        if ($this->shipmentFinder->latestByWaybill($number) !== null) {
            return;
        }
        $remote = $this->remoteOrderMatcher->findByWaybill($number);
        if ($remote === null) {
            return;
        }
        $orderId = (int)$this->magentoShipmentRepository->get($magentoShipmentId)->getOrderId();
        $order = $this->orderLoader->get($orderId);
        if (!$this->remoteOrderMatcher->isMadeFor($order, $remote)) {
            $this->logger->info('A tracking number belongs to a Polkurier order made for another', [
                'order' => $order->getIncrementId(),
                'number' => $number,
                'polkurier_number' => $remote->getNumber(),
            ]);

            return;
        }
        $this->orderLock->run($orderId, function () use ($order, $magentoShipmentId, $remote): void {
            $held = $this->shipmentFinder->byNumbers([(string)$remote->getNumber()])[0] ?? null;
            if ($held === null) {
                $this->link($order, $magentoShipmentId, $remote);
            } elseif ($held->getOrderId() === (int)$order->getEntityId()) {
                // Another of its waybills, added before the module read it.
                $this->nativeShipmentService->takeOverTracks($order, $magentoShipmentId, $held);
                $this->statusRefreshService->refresh((int)$held->getId());
            }
        });
    }

    /**
     * @throws LocalizedException
     */
    private function link(MagentoOrder $order, int $magentoShipmentId, Order $remote): void
    {
        $packs = 0;
        foreach ($remote->getPacks() as $pack) {
            $packs += $pack->getAmount();
        }
        $free = isset($this->nativeShipmentService->getFreeShipments($order)[$magentoShipmentId]);
        $shipment = $this->shipmentFactory->create()
            ->setOrderId((int)$order->getEntityId())
            ->setStoreId((int)$order->getStoreId())
            ->setState(ShipmentInterface::STATE_UNKNOWN)
            ->setCarrierCode($remote->getCourier())
            ->setDescription((string)$remote->getDescription())
            ->setRecipientPostcode($remote->getRecipient()?->getPostcode())
            ->setRecipientPhone($remote->getRecipient()?->getPhone())
            ->setPackCount(\max(1, $packs))
            ->setExternalOrderId($remote->getExternalOrderId())
            ->setShipmentId($free ? $magentoShipmentId : null);
        // Placed, or cancelled since in the Polkurier panel: as the order says.
        $this->remoteOrderApplier->apply($remote, $shipment);
        $this->shipmentRepository->save($shipment);
        $this->nativeShipmentService->takeOverTracks($order, $magentoShipmentId, $shipment);
        if ($shipment->getState() === ShipmentInterface::STATE_CANCELLED) {
            $this->nativeFollowUpService->comment($order, __(
                'Linked with Polkurier order %1, cancelled in the Polkurier panel.',
                $shipment->getPolkurierNumber(),
            ));

            return;
        }
        if ($free) {
            $this->nativeShipmentService->addTracks($order, $magentoShipmentId, $shipment, $shipment->getWaybills());
            $this->nativeFollowUpService->remarkLinked($shipment);
        }
        $this->nativeFollowUpService->notePlaced($order, $shipment, true);
        $this->nativeFollowUpService->linked($shipment, $order, 'track');
    }
}
