<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Carrier\CarrierNames;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;

/**
 * A shipment that wasn't placed, because Polkurier or the connection failed, waits for the
 * merchant only so long: it's placed ("Send again", or the order's next attempt) or discarded. A
 * day after its last attempt the admin is told about it (Model\Admin\AttentionMessage), and a day
 * later still it's removed. None stays "Not placed" for good. Each removal is noted in the order's
 * history.
 */
class NotPlaced
{
    /**
     * From its last attempt to the admin's notice of it.
     */
    public const int NOTICE_AFTER_HOURS = 24;

    /**
     * From its last attempt to its removal.
     */
    public const int LIFETIME_HOURS = 48;

    public function __construct(
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly MagentoFollowUp $followUp,
        private readonly CarrierNames $carrierNames,
        private readonly DateTime $dateTime,
    ) {}

    /**
     * When it's removed unless placed or discarded first, UTC.
     */
    public function expiresAt(ShipmentInterface $shipment): string
    {
        return $this->expiresAfter((string)$shipment->getCreatedAt());
    }

    /**
     * The same, from when it was last tried, UTC.
     */
    public function expiresAfter(string $lastTried): string
    {
        $from = \strtotime($lastTried . ' UTC') ?: $this->dateTime->gmtTimestamp();

        return \gmdate('Y-m-d H:i:s', $from + self::LIFETIME_HOURS * 3600);
    }

    /**
     * @throws LocalizedException when it's not a shipment that wasn't placed
     */
    public function discard(int $shipmentId): ShipmentInterface
    {
        $shipment = $this->shipmentRepository->get($shipmentId);
        if ($shipment->getState() !== ShipmentInterface::STATE_FAILED) {
            throw new LocalizedException(\__('Only a shipment that wasn\'t placed can be discarded.'));
        }
        $this->remove($shipment, \__(
            'The %1 shipment that wasn\'t placed was discarded.',
            $this->carrierNames->get($shipment->getCarrierCode()),
        ));

        return $shipment;
    }

    /**
     * Removes those whose last attempt is a day old.
     *
     * @return int how many were removed
     */
    public function expire(): int
    {
        $before = \gmdate('Y-m-d H:i:s', $this->dateTime->gmtTimestamp() - self::LIFETIME_HOURS * 3600);
        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::STATE, ShipmentInterface::STATE_FAILED)
            ->addFieldToFilter(ShipmentInterface::CREATED_AT, ['lt' => $before]);
        $removed = 0;
        foreach ($collection->getItems() as $shipment) {
            /** @var ShipmentInterface $shipment */
            $this->remove($shipment, \__(
                'The %1 shipment that wasn\'t placed was removed, %2 hours after it was last tried. Why it wasn\'t placed: %3',
                $this->carrierNames->get($shipment->getCarrierCode()),
                self::LIFETIME_HOURS,
                \rtrim((string)$shipment->getLastError(), '. '),
            ));
            $removed++;
        }

        return $removed;
    }

    private function remove(ShipmentInterface $shipment, \Magento\Framework\Phrase $comment): void
    {
        $this->shipmentRepository->delete($shipment);
        try {
            $this->followUp->comment($this->followUp->order($shipment->getOrderId()), $comment);
        } catch (LocalizedException) {
            // The order is gone: nothing to note it on.
        }
    }
}
