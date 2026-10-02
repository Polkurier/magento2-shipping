<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Phrase;
use Magento\Sales\Api\Data\ShipmentCommentInterface;
use Magento\Sales\Api\Data\ShipmentCommentInterfaceFactory;
use Magento\Sales\Api\ShipmentCommentRepositoryInterface;

/**
 * Adds an internal remark to a Magento shipment's history: the customer isn't notified, and it
 * isn't shown on the storefront. Only the remark is saved, not the shipment.
 */
class ShipmentComments
{
    public function __construct(
        private readonly ShipmentCommentInterfaceFactory $commentFactory,
        private readonly ShipmentCommentRepositoryInterface $commentRepository,
    ) {}

    /**
     * @throws CouldNotSaveException
     */
    public function add(int $magentoShipmentId, Phrase|string $comment): void
    {
        /** @var ShipmentCommentInterface $remark */
        $remark = $this->commentFactory->create();
        $remark->setParentId($magentoShipmentId)
            ->setComment((string)$comment)
            ->setIsCustomerNotified(0)
            ->setIsVisibleOnFront(0);
        $this->commentRepository->save($remark);
    }
}
