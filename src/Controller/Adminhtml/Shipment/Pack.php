<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\Shipment\AutoPacker;
use Polkurier\Shipping\Model\Shipment\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\MagentoShipment;
use Polkurier\Shipping\Model\Shipment\OwnOrders;

/**
 * Packs the items shipped into parcels of the pack templates, for the shipment screen to put in
 * place of its parcels, or Magento's packaging window in place of its packages.
 */
class Pack extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly OwnOrders $ownOrders,
        private readonly DraftHydrator $hydrator,
        private readonly MagentoShipment $magentoShipment,
        private readonly AutoPacker $autoPacker,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $data = (array)$this->getRequest()->getParam('data', []);
        $order = $this->ownOrders->get((int)($data['order_id'] ?? 0));
        $draft = $this->hydrator->fromArray($data);
        $targetId = $draft->getTargetShipmentId();
        // A Magento shipment there is carries what it does, which the order counts as shipped.
        $qtys = $targetId !== null
            ? $this->magentoShipment->getShipmentQuantities($order, $targetId)
            : ($this->magentoShipment->getQuantities($order, $draft->getItems()) ?: $this->magentoShipment->getQuantitiesLeft($order));
        if ($qtys === [] && $targetId !== null) {
            throw new LocalizedException(\__('The shipment to create a label for is missing.'));
        }
        if ($qtys === []) {
            throw new LocalizedException(\__('Everything in this order is shipped already.'));
        }
        // Repacking the parcels there are keeps to their kind; without any, the items decide.
        $packed = $draft->getShipmentType() !== ''
            ? $this->autoPacker->pack($order, $qtys, $draft->getShipmentType())
            : $this->autoPacker->packByItems($order, $qtys);
        if ($packed['packs'] === []) {
            throw new LocalizedException($packed['unmeasured'] !== []
                ? \__('Add shipping dimensions to these products to pack them automatically: %1.', \implode(', ', $packed['unmeasured']))
                : \__('Add a pack template with its size to pack the items automatically.'));
        }
        $rows = $this->hydrator->toArray($draft->setPacks($packed['packs']));

        return [
            'packs' => $rows[ShipmentDraftInterface::PACKS],
            'estimated' => true,
            'message' => $this->describeUnweighed($packed['unweighed'], $packed['unweighed_own']),
        ];
    }

    /**
     * What the parcels' weights leave out: a parcel of a pack template takes the template's weight,
     * one of its own size has none to take.
     *
     * @param string[] $unweighed the products without a weight
     * @param string[] $own those of them in a parcel of its own size
     */
    private function describeUnweighed(array $unweighed, array $own): string
    {
        $templated = \array_values(\array_diff($unweighed, $own));
        $lines = [];
        if ($templated !== []) {
            $lines[] = (string)\__('These products have no weight, so their parcels take the pack template\'s weight: %1.', \implode(', ', $templated));
        }
        if ($own !== []) {
            $lines[] = (string)\__('These products have no weight and their parcels have no pack template, so enter those parcels\' weights yourself: %1.', \implode(', ', $own));
        }

        return \implode(' ', $lines);
    }
}
