<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Packing\AutoPacker;

/**
 * Packs the shipped items into parcels based on the pack templates. The shipment screen puts them
 * in place of its parcels; Magento's packaging window puts them in place of its packages.
 */
class Pack extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly OrderLoader $orderLoader,
        private readonly DraftHydrator $draftHydrator,
        private readonly AutoPacker $autoPacker,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $data = (array)$this->getRequest()->getParam('data', []);
        $order = $this->orderLoader->getForPolkurier((int)($data['order_id'] ?? 0));
        $draft = $this->draftHydrator->createFromArray($data);
        ['packs' => $packs, 'notice' => $notice] = $this->autoPacker->packDraft($order, $draft);
        $rows = $this->draftHydrator->convertToArray($draft->setPacks($packs));

        return [
            'packs' => $rows[ShipmentDraftInterface::FIELD_PACKS],
            'message' => $notice,
        ];
    }
}
