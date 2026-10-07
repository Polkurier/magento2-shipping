<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Native;

use Magento\Framework\DataObject;
use Magento\Framework\DataObjectFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\DocumentService;
use Polkurier\Shipping\Model\Shipment\Draft\DefaultDraftBuilder;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\Placement\PlacementService;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Psr\Log\LoggerInterface;

/**
 * Shipping labels from Magento's own shipment page.
 *
 * Magento hands the carrier the packages from its packaging popup. They become the parcels of one
 * Polkurier order; everything else comes from the order and the configured defaults, as for the
 * shipment screen, adjusted by the options posted from the shipment page. For a Magento shipment
 * that already has a Polkurier order, the label of that order is fetched instead.
 *
 * The Polkurier order is linked to the Magento shipment once Magento has saved it.
 *
 * Magento's shipment pages ask the carrier without saying which order they're for, and post the
 * options with the request; Plugin\Adminhtml\NativeLabelPagePlugin answers for the order, and
 * puts the options on the Magento shipment (FIELD_OPTIONS) before the label is asked for.
 */
class NativeLabelService implements ResetAfterRequestInterface
{
    /**
     * Data key on the Magento shipment: the Polkurier shipment to link once it is saved.
     */
    public const string FIELD_LINK = 'polkurier_shipment_id';

    /**
     * Data key on the Magento shipment: the label was not available, so none is stored.
     */
    public const string FIELD_NO_LABEL = 'polkurier_no_label';

    /**
     * Data key on the Magento shipment: the carrier its Polkurier order went with, which titles the
     * tracks Magento adds for the label (Observer\PrepareLabelledShipmentObserver).
     */
    public const string FIELD_CARRIER = 'polkurier_carrier_code';

    /**
     * Data key on the Magento shipment: the options posted from the shipment page, as the request
     * carried them under OPTIONS_PARAM.
     */
    public const string FIELD_OPTIONS = 'polkurier_label_options';

    /**
     * Request parameter carrying the options set on the shipment page.
     */
    public const string OPTIONS_PARAM = 'polkurier';

    /**
     * Draft fields the shipment page may set. Recipient and parcels come from the order and the
     * packaging popup. A list: the page gets it as a JSON array.
     */
    public const array OPTION_FIELDS = [
        ShipmentDraftInterface::FIELD_CARRIER_CODE,
        ShipmentDraftInterface::FIELD_DESCRIPTION,
        ShipmentDraftInterface::FIELD_SENDER_ADDRESS_ID,
        ShipmentDraftInterface::FIELD_SENDER_POINT_ID,
        ShipmentDraftInterface::FIELD_RECIPIENT_POINT_ID,
        ShipmentDraftInterface::FIELD_COD_AMOUNT,
        ShipmentDraftInterface::FIELD_COD_TYPE,
        ShipmentDraftInterface::FIELD_COD_RETURN_TYPE,
        ShipmentDraftInterface::FIELD_COD_BANK_ACCOUNT_ID,
        ShipmentDraftInterface::FIELD_INSURANCE_AMOUNT,
        ShipmentDraftInterface::FIELD_SERVICES,
        ShipmentDraftInterface::FIELD_COVER_ADDRESS_ID,
        ShipmentDraftInterface::FIELD_PICKUP_MODE,
        ShipmentDraftInterface::FIELD_PICKUP_DATE,
        ShipmentDraftInterface::FIELD_PICKUP_TIME_FROM,
        ShipmentDraftInterface::FIELD_PICKUP_TIME_TO,
        // A sender or bank account typed in, and whether it's kept.
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::FIELD_PERSON],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::FIELD_COMPANY],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::FIELD_STREET],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::FIELD_HOUSE_NUMBER],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::FIELD_FLAT_NUMBER],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::FIELD_POSTCODE],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::FIELD_CITY],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::FIELD_COUNTRY],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::FIELD_PHONE],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::FIELD_EMAIL],
        ShipmentDraftInterface::FIELD_KEEP_NEW_SENDER,
        ShipmentDraftInterface::FIELD_NEW_SENDER_NAME,
        ShipmentDraftInterface::FIELD_NEW_BANK_ACCOUNT_NUMBER,
        ShipmentDraftInterface::FIELD_KEEP_NEW_BANK_ACCOUNT,
        ShipmentDraftInterface::FIELD_NEW_BANK_ACCOUNT_NAME,
    ];

    private ?string $unreadyLabel = null;

    public function __construct(
        private readonly ConfigProvider $configProvider,
        private readonly NativeLabelPackageMapper $nativeLabelPackageMapper,
        private readonly PlacementService $placementService,
        private readonly DefaultDraftBuilder $draftBuilder,
        private readonly DraftHydrator $hydrator,
        private readonly ShipmentFinder $finder,
        private readonly DocumentService $documentService,
        private readonly NativeShipmentService $nativeShipmentService,
        private readonly TrackFactory $trackFactory,
        private readonly LoggerInterface $logger,
        private readonly DataObjectFactory $dataObjectFactory,
    ) {}

    /**
     * Whether labels are on for the store. Asked without one, as Magento's shipment pages and its
     * label generator ask the carrier, it's yes: the order is known only there, where
     * NativeLabelPagePlugin answers for it, and request() checks it again.
     */
    public function isAvailable(?int $storeId): bool
    {
        return $storeId === null || $this->configProvider->isNativeLabelsEnabled($storeId);
    }

    /**
     * Answers Magento's label request: one entry with every waybill and the label covering them.
     *
     * @throws LocalizedException when no order could be placed
     */
    public function request(DataObject $request): DataObject
    {
        $magentoShipment = $request->getData('order_shipment');
        if (!$magentoShipment instanceof MagentoShipmentModel) {
            throw new LocalizedException(__('The shipment to create a label for is missing.'));
        }
        $order = $magentoShipment->getOrder();
        if (!$this->configProvider->isNativeLabelsEnabled((int)$order->getStoreId())) {
            throw new LocalizedException(__(
                'Shipping labels on Magento\'s shipment page are turned off for this store.',
            ));
        }

        $shipment = $magentoShipment->getId() ? $this->findLinked((int)$magentoShipment->getId()) : null;
        if ($shipment === null) {
            $draft = $this->buildDraft($magentoShipment, $order, (array)$request->getData('packages'));
            $shipment = $this->placementService->forNativeShipment(
                (int)$order->getEntityId(),
                $draft,
                $magentoShipment->getId() ? (int)$magentoShipment->getId() : null,
            );
            $magentoShipment->setData(self::FIELD_LINK, (int)$shipment->getId());
        }
        // Magento saves it once the label is done: with the number the order was sent with.
        if ($shipment->getReservedShipmentNumber() !== null && !$magentoShipment->getIncrementId()) {
            $magentoShipment->setIncrementId($shipment->getReservedShipmentNumber());
        }

        return $this->answer($magentoShipment, $order, $shipment);
    }

    /**
     * The Polkurier order of the shipment just labelled, when its label wasn't ready to go with
     * it; asked once, by what tells the admin about the shipment.
     */
    public function takeUnreadyLabel(): ?string
    {
        $number = $this->unreadyLabel;
        $this->unreadyLabel = null;

        return $number;
    }

    public function _resetState(): void
    {
        $this->unreadyLabel = null;
    }

    /**
     * @param array<int|string, mixed> $packages
     */
    private function buildDraft(
        MagentoShipmentModel $magentoShipment,
        Order $order,
        array $packages,
    ): ShipmentDraftInterface {
        $qtys = [];
        foreach ($magentoShipment->getAllItems() as $item) {
            $qtys[(int)$item->getOrderItemId()] = (float)$item->getQty();
        }
        $data = \array_replace(
            $this->hydrator->toArray($this->draftBuilder->createFromOrder($order, $qtys)),
            $this->options($magentoShipment),
        );
        $draft = $this->hydrator->fromArray($data);
        $packs = $this->nativeLabelPackageMapper->toPacks($packages, $qtys);
        if ($packs !== []) {
            $draft->setPacks($packs);
        }

        return $draft;
    }

    /**
     * Magento adds a track per waybill that comes with a label. Without a label it adds none, so
     * the tracks are added here, and the shipment is marked to be saved without a label.
     */
    private function answer(
        MagentoShipmentModel $magentoShipment,
        Order $order,
        ShipmentInterface $shipment,
    ): DataObject {
        $magentoShipment->setData(self::FIELD_CARRIER, $shipment->getCarrierCode());
        $waybills = $shipment->getWaybills();
        $label = $waybills === [] ? null : $this->fetchLabel($shipment);
        if ($label !== null) {
            return $this->dataObjectFactory->create([
                'data' => ['info' => [['tracking_number' => $waybills, 'label_content' => $label]]],
            ]);
        }

        $known = [];
        foreach ((array)$magentoShipment->getTracks() as $track) {
            $known[] = (string)$track->getTrackNumber();
        }
        foreach (\array_diff($waybills, $known) as $waybill) {
            $track = $this->trackFactory->create();
            $track->setNumber($waybill);
            $track->setCarrierCode(Polkurier::CODE);
            $track->setTitle($this->nativeShipmentService->getTrackTitle($order, $shipment->getCarrierCode()));
            $magentoShipment->addTrack($track);
        }
        $magentoShipment->setData(self::FIELD_NO_LABEL, true);
        // Told in Magento's own message about the shipment (Plugin\Adminhtml\ShipmentSavePlugin).
        $this->unreadyLabel = (string)$shipment->getPolkurierNumber();

        return $this->dataObjectFactory->create(['data' => ['info' => []]]);
    }

    private function fetchLabel(ShipmentInterface $shipment): ?string
    {
        try {
            $content = $this->documentService->getLabels([$shipment], true)['content'];
        } catch (LocalizedException $e) {
            $this->logger->warning('Polkurier label not available for the Magento shipment', [
                'polkurier_number' => $shipment->getPolkurierNumber(),
                'exception' => $e,
            ]);

            return null;
        }

        return \str_contains($content, '%PDF-') ? $content : null;
    }

    private function findLinked(int $magentoShipmentId): ?ShipmentInterface
    {
        return $this->finder->latestLinkedTo($magentoShipmentId, [ShipmentInterface::STATE_CREATED]);
    }

    /**
     * The options posted from the shipment page, limited to OPTION_FIELDS.
     *
     * @return array<string, mixed>
     */
    private function options(MagentoShipmentModel $magentoShipment): array
    {
        $options = $magentoShipment->getData(self::FIELD_OPTIONS);

        return \is_array($options) ? \array_intersect_key($options, \array_flip(self::OPTION_FIELDS)) : [];
    }
}
