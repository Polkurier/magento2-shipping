<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Native;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\OrderStatusHistoryInterface;
use Magento\Sales\Api\Data\ShipmentCommentInterface;
use Magento\Sales\Api\Data\ShipmentCommentInterfaceFactory;
use Magento\Sales\Api\Data\ShipmentInterface as MagentoShipmentInterface;
use Magento\Sales\Api\OrderStatusHistoryRepositoryInterface;
use Magento\Sales\Api\ShipmentCommentRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;
use Magento\Sales\Helper\Data as SalesData;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Email\Sender\ShipmentSender;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Api\ShippingChoiceRepositoryInterface;
use Polkurier\Shipping\Enum\Sdk\ApiEventEnum;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierNameProvider;
use Polkurier\Shipping\Model\Sdk\ApiEventDispatcher;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;

/**
 * What follows on the Magento side once a Polkurier order is known: the Magento order's shipment
 * and comments, and the event that reports it. None of it may undo the Polkurier order, so
 * failures here are recorded or logged rather than thrown.
 */
readonly class NativeFollowUpService
{
    /**
     * Class of the links in comments that lead outside the admin.
     */
    public const string EXTERNAL_LINK = 'polkurier-external';

    /**
     * Magento's setting for sending sales e-mails from its cron job rather than at once.
     */
    private const string ASYNC_SENDING = 'sales_email/general/async_sending';

    public function __construct(
        private OrderLoader $orderLoader,
        private ShipmentRepositoryInterface $shipmentRepository,
        private NativeShipmentService $nativeShipmentService,
        private MagentoShipmentRepository $magentoShipmentRepository,
        private ShipmentFinder $shipmentFinder,
        private OrderStatusHistoryRepositoryInterface $historyRepository,
        private ShipmentCommentInterfaceFactory $commentFactory,
        private ShipmentCommentRepositoryInterface $commentRepository,
        private ApiEventDispatcher $apiEventDispatcher,
        private Logger $logger,
        private CarrierNameProvider $carrierNameProvider,
        private ShipmentSender $shipmentSender,
        private SalesData $salesData,
        private ScopeConfigInterface $scopeConfig,
        private ShippingChoiceRepositoryInterface $shippingChoiceRepository,
    ) {}

    /**
     * Ships the Magento order with the Polkurier order's waybills. A failure is recorded on the
     * shipment, to be tried again, rather than thrown.
     *
     * The customer's e-mail about the Magento shipment goes last, once the Magento shipment is
     * linked, and only for a placed Polkurier order with waybills (notifyCustomer()).
     *
     * @return string|null why the Magento shipment could not be created, or null
     */
    public function ship(Order $order, ShipmentInterface $shipment): ?string
    {
        try {
            $shipmentId = $this->nativeShipmentService->ship(
                $order,
                $shipment,
                $shipment->getShipItems(),
                $shipment->getTargetShipmentId(),
                $shipment->getShipPackages(),
            );
            $this->shipmentRepository->save($shipment->setShipmentId($shipmentId));
            $this->addLinkedRemark($shipment);
        } catch (\Throwable $e) {
            $this->logger->error('Polkurier order placed but the Magento shipment failed', [
                'order' => $order->getIncrementId(),
                'exception' => $e,
            ]);
            // Still unlinked: another Polkurier order may have taken the Magento shipment meanwhile.
            $shipment->setShipmentId(null)
                ->setLastError((string)__('The Magento shipment could not be created: %1', $e->getMessage()));
            $this->shipmentRepository->save($shipment);

            return $e->getMessage();
        }
        $this->notifyCustomer($order, $shipment);

        return null;
    }

    /**
     * Sends the customer Magento's e-mail about the Magento shipment, when it was asked for with the
     * Polkurier order ({@see ShipmentDraftInterface::FIELD_NOTIFY_CUSTOMER}). Only for a placed order
     * whose waybills are on the Magento shipment, while no e-mail about that shipment was sent or asked
     * for, and while the store sends shipment e-mails: pending, unclear, refused and cancelled orders
     * e-mail nobody, and an order whose carrier issues its waybills later e-mails once they're added
     * ({@see \Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService}). A failure is logged; the
     * shipment stands either way.
     */
    public function notifyCustomer(Order $order, ShipmentInterface $shipment): void
    {
        $storeId = (int)$order->getStoreId();
        $magentoShipmentId = $shipment->getShipmentId();
        if ($shipment->getState() !== ShipmentInterface::STATE_CREATED
            || $magentoShipmentId === null
            || $shipment->getWaybills() === []
            || empty($shipment->getDraft()[ShipmentDraftInterface::FIELD_NOTIFY_CUSTOMER])
            || !$this->salesData->canSendNewShipmentEmail($storeId)
        ) {
            return;
        }
        try {
            $magentoShipment = $this->magentoShipmentRepository->get($magentoShipmentId);
            if (!$magentoShipment instanceof MagentoShipmentModel
                || !NativeShipmentService::isEmailOpen($magentoShipment)
            ) {
                return;
            }
            // False also when Magento's asynchronous sending queues it; only otherwise did it fail.
            if (!$this->shipmentSender->send($magentoShipment)
                && !$this->scopeConfig->isSetFlag(self::ASYNC_SENDING)
            ) {
                $this->logger->warning('Magento shipment created but the e-mail to the customer was not sent', [
                    'order' => $order->getIncrementId(),
                    'shipment' => $magentoShipment->getIncrementId(),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Magento shipment created but the e-mail to the customer failed', [
                'order' => $order->getIncrementId(),
                'exception' => $e,
            ]);
        }
    }

    /**
     * Removes the label, packages and waybills from the Magento shipment of a cancelled Polkurier
     * order, so another Polkurier order can go on it. The shipment stays, with its items, so
     * another order can be placed from its page ("Create Shipping Label") or the shipment screen. A
     * shipment held by another standing Polkurier order, or belonging to another order, is left as
     * it is. A failure is logged rather than thrown: the Polkurier order is cancelled either way.
     *
     * @return string|null the number of the Magento shipment cleared, or null
     */
    public function clearShipment(Order $order, ShipmentInterface $shipment): ?string
    {
        try {
            return $this->clearLabel($order, $shipment);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Polkurier order cancelled but its label could not be removed from the Magento shipment',
                [
                    'order' => $order->getIncrementId(),
                    'exception' => $e,
                ],
            );

            return null;
        }
    }

    /**
     * What follows a Polkurier order's cancellation in Magento: its Magento shipment loses the
     * label and tracking numbers, to take another Polkurier order, and the order and shipment say so.
     *
     * @param bool $inPolkurier cancelled in the Polkurier panel rather than here
     *
     * @return Order the order it belongs to
     */
    public function followCancellation(ShipmentInterface $shipment, bool $inPolkurier): Order
    {
        $number = (string)$shipment->getPolkurierNumber();
        $order = $this->orderLoader->get($shipment->getOrderId());
        $cleared = $this->clearShipment($order, $shipment);
        $this->addRemark(
            $shipment->getShipmentId(),
            $inPolkurier
                ? __(
                    'Polkurier order %1 cancelled in the Polkurier panel. Label and tracking numbers removed.',
                    $number,
                )
                : __('Polkurier order %1 cancelled. Label and tracking numbers removed.', $number),
        );
        $this->addComment($order, match (true) {
            $inPolkurier && $cleared !== null => __(
                'Polkurier order %1 cancelled in the Polkurier panel. '
                . 'Magento shipment #%2 kept, without label and tracking numbers.',
                $number,
                $cleared,
            ),
            $inPolkurier => __('Polkurier order %1 cancelled in the Polkurier panel.', $number),
            $cleared !== null => __(
                'Polkurier order %1 cancelled. Magento shipment #%2 kept, without label and tracking numbers.',
                $number,
                $cleared,
            ),
            default => __('Polkurier order %1 cancelled.', $number),
        });

        return $order;
    }

    public function addComment(Order $order, Phrase $comment): void
    {
        try {
            $history = $order->addCommentToStatusHistory((string)$comment);
            // Saved alone, not with the whole order. Declared to return the comment, without a
            // return type.
            if ($history instanceof OrderStatusHistoryInterface) {
                $this->historyRepository->save($history);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Could not add the Polkurier comment to the order', ['exception' => $e]);
        }
    }

    /**
     * An internal remark in the Magento shipment's history, never shown to the customer.
     */
    public function addRemark(?int $magentoShipmentId, Phrase $remark): void
    {
        if ($magentoShipmentId === null) {
            return;
        }
        try {
            /** @var ShipmentCommentInterface $comment */
            $comment = $this->commentFactory->create();
            $comment->setParentId($magentoShipmentId)
                ->setComment((string)$remark)
                ->setIsCustomerNotified(0)
                ->setIsVisibleOnFront(0);
            $this->commentRepository->save($comment);
        } catch (\Throwable $e) {
            $this->logger->warning('Could not add the Polkurier remark to the Magento shipment', ['exception' => $e]);
        }
    }

    /**
     * The remark for a Polkurier order going with a Magento shipment.
     */
    public function addLinkedRemark(ShipmentInterface $shipment): void
    {
        $this->addRemark($shipment->getShipmentId(), __(
            'Linked with Polkurier order %1. Carrier: %2. Waybills: %3.',
            (string)$shipment->getPolkurierNumber(),
            $this->carrierNameProvider->get($shipment->getCarrierCode()),
            $this->formatWaybillList($shipment),
        ));
    }

    /**
     * Notes a placed Polkurier order on the order: its number, carrier and waybills, and what the
     * customer chose when it went with another carrier or point
     * ({@see \Polkurier\Shipping\Model\Shipment\Native\ActualShippingUpdater}).
     *
     * @param bool $byHand linked by hand to an unconfirmed attempt rather than placed or found; noted
     *     as linked, as on the Magento shipment
     */
    public function notePlaced(Order $order, ShipmentInterface $shipment, bool $byHand = false): void
    {
        $number = (string)$shipment->getPolkurierNumber();
        $carrier = $this->carrierNameProvider->get($shipment->getCarrierCode());
        $waybills = $this->formatWaybillList($shipment);
        $choice = $this->shippingChoiceRepository->getForOrder((int)$order->getEntityId())?->getShippingDescription();
        if ($choice === null || $choice === '') {
            $this->addComment($order, $byHand
                ? __('Linked with Polkurier order %1. Carrier: %2. Waybills: %3.', $number, $carrier, $waybills)
                : __('Polkurier order %1 placed. Carrier: %2. Waybills: %3.', $number, $carrier, $waybills));

            return;
        }
        $this->addComment($order, $byHand
            ? __(
                'Linked with Polkurier order %1. Carrier: %2. Waybills: %3. Customer\'s choice: %4.',
                $number,
                $carrier,
                $waybills,
                $choice,
            )
            : __(
                'Polkurier order %1 placed. Carrier: %2. Waybills: %3. Customer\'s choice: %4.',
                $number,
                $carrier,
                $waybills,
                $choice,
            ));
    }

    /**
     * The waybills as comments show them: each linked to the carrier's tracking page, when Polkurier
     * gave one. Magento keeps links in order and shipment comments, but only some of their
     * attributes: the class lets the page open them in a new tab
     * ({@see \Polkurier\Shipping\Plugin\Adminhtml\ExternalCommentLinksPlugin}).
     *
     * @param string[]|null $waybills some of the shipment's, or all of them
     */
    public function formatWaybillList(ShipmentInterface $shipment, ?array $waybills = null): string
    {
        $waybills ??= $shipment->getWaybills();
        if ($waybills === []) {
            return (string)__('not issued yet');
        }
        $url = (string)$shipment->getTrackingUrl();

        return \implode(', ', \array_map(
            static fn(string $waybill): string => $url === '' ? $waybill : \sprintf(
                '<a href="%s" class="' . self::EXTERNAL_LINK . '" target="_blank" rel="noopener">%s</a>',
                \htmlspecialchars($url, \ENT_QUOTES),
                \htmlspecialchars($waybill, \ENT_QUOTES),
            ),
            $waybills,
        ));
    }

    /**
     * An unconfirmed attempt turned out to have been placed, or one placed elsewhere was linked from
     * its waybill: reported like any placed order.
     *
     * @param string $source how it was found: "reconcile", "adopt" or "track"
     */
    public function followLink(ShipmentInterface $shipment, Order $order, string $source): void
    {
        $this->apiEventDispatcher->dispatch(ApiEventEnum::OrderCreateAfter, $shipment->getStoreId(), [
            'shipment' => $shipment,
            'order' => $order,
            'source' => $source,
            'request' => null,
            'response' => null,
        ]);
    }

    /**
     * @return string|null the number of the Magento shipment cleared; null when it's left as it
     *     is, because another Polkurier order holds it or it isn't the order's
     * @throws \Exception when it can't be cleared
     */
    private function clearLabel(Order $order, ShipmentInterface $cancelled): ?string
    {
        $magentoShipmentId = $cancelled->getShipmentId();
        if ($magentoShipmentId === null) {
            return null;
        }
        $magentoShipment = $this->magentoShipmentRepository->get($magentoShipmentId);
        if ((int)$magentoShipment->getOrderId() !== (int)$order->getEntityId()) {
            return null;
        }
        foreach ($this->shipmentFinder->findLinkedTo([$magentoShipmentId]) as $polkurier) {
            if ((int)$polkurier->getId() !== (int)$cancelled->getId()
                && \in_array($polkurier->getState(), ShipmentInterface::LIVE_STATES, true)
            ) {
                return null;
            }
        }

        $this->nativeShipmentService->removeTracks($magentoShipment, $cancelled->getWaybills());
        // Magento offers "Create Shipping Label" again once there's none.
        if ($magentoShipment instanceof MagentoShipmentModel) {
            $magentoShipment->setData(MagentoShipmentInterface::SHIPPING_LABEL, null);
            $magentoShipment->setData(MagentoShipmentInterface::PACKAGES, null);
            $this->magentoShipmentRepository->save($magentoShipment);
        }

        return (string)$magentoShipment->getIncrementId();
    }
}
