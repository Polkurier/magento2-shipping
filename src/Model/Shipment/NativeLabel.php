<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Measure\Length;
use Magento\Framework\Measure\Weight;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\Shipment\Draft\ItemFactory;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Shipping\Model\StoreWeightUnit;
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
 */
class NativeLabel
{
    /**
     * Data key on the Magento shipment: the Polkurier shipment to link once it is saved.
     */
    public const string LINK = 'polkurier_shipment_id';

    /**
     * Data key on the Magento shipment: the label was not available, so none is stored.
     */
    public const string NO_LABEL = 'polkurier_no_label';

    /**
     * Data key on the Magento shipment: the carrier its Polkurier order went with, which titles the
     * tracks Magento adds for the label (Observer\PrepareLabelledShipmentObserver).
     */
    public const string CARRIER = 'polkurier_carrier_code';

    /**
     * Request parameter carrying the options set on the shipment page.
     */
    public const string OPTIONS_PARAM = 'polkurier';

    /**
     * Draft fields the shipment page may set. Recipient and parcels come from the order and the
     * packaging popup. A list: the page gets it as a JSON array.
     */
    public const array OPTION_FIELDS = [
        ShipmentDraftInterface::CARRIER_CODE,
        ShipmentDraftInterface::DESCRIPTION,
        ShipmentDraftInterface::SENDER_ADDRESS_ID,
        ShipmentDraftInterface::SENDER_POINT_ID,
        ShipmentDraftInterface::RECIPIENT_POINT_ID,
        ShipmentDraftInterface::COD_AMOUNT,
        ShipmentDraftInterface::COD_TYPE,
        ShipmentDraftInterface::COD_RETURN_TYPE,
        ShipmentDraftInterface::COD_BANK_ACCOUNT_ID,
        ShipmentDraftInterface::INSURANCE_AMOUNT,
        ShipmentDraftInterface::SERVICES,
        ShipmentDraftInterface::COVER_ADDRESS_ID,
        ShipmentDraftInterface::PICKUP_MODE,
        ShipmentDraftInterface::PICKUP_DATE,
        ShipmentDraftInterface::PICKUP_TIME_FROM,
        ShipmentDraftInterface::PICKUP_TIME_TO,
        // A sender or bank account typed in, and whether it's kept.
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::PERSON],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::COMPANY],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::STREET],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::HOUSE_NUMBER],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::FLAT_NUMBER],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::POSTCODE],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::CITY],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::COUNTRY],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::PHONE],
        ShipmentDraftInterface::NEW_SENDER_FIELDS[AddressInterface::EMAIL],
        ShipmentDraftInterface::KEEP_NEW_SENDER,
        ShipmentDraftInterface::NEW_SENDER_NAME,
        ShipmentDraftInterface::NEW_BANK_ACCOUNT_NUMBER,
        ShipmentDraftInterface::KEEP_NEW_BANK_ACCOUNT,
        ShipmentDraftInterface::NEW_BANK_ACCOUNT_NAME,
    ];

    /**
     * A package's name to save it as a pack template under, which the packaging window adds to the
     * package's params.
     */
    public const string KEEP_AS_PARAM = 'polkurier_keep_as';

    private const float CM_PER_INCH = 2.54;

    private ?string $unreadyLabel = null;

    public function __construct(
        private readonly Config $config,
        private readonly AuthorizationInterface $authorization,
        private readonly RequestInterface $request,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly MagentoShipmentRepository $magentoShipmentRepository,
        private readonly Placing $placing,
        private readonly DefaultDraftBuilder $draftBuilder,
        private readonly DraftHydrator $hydrator,
        private readonly PackFactory $packFactory,
        private readonly PackType $packTypes,
        private readonly ShipmentFinder $finder,
        private readonly Documents $documents,
        private readonly MagentoShipment $magentoShipment,
        private readonly TrackFactory $trackFactory,
        private readonly LoggerInterface $logger,
        private readonly ItemFactory $itemFactory,
    ) {}

    public function isAvailable(?int $storeId): bool
    {
        return $this->config->isNativeLabelsEnabled($storeId ?? $this->getContextStoreId());
    }

    /**
     * The parcel types, as the packaging window's package types. Pack templates are offered
     * separately, next to them, and only fill in a package.
     *
     * @return array<string, string>
     */
    public function getContainerTypes(): array
    {
        return $this->packTypes->toArray();
    }

    /**
     * Every parcel type takes the size entered in the packaging window.
     *
     * @return string[]
     */
    public function getCustomizableContainerTypes(): array
    {
        return \array_map('strval', \array_keys($this->packTypes->toArray()));
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
            throw new LocalizedException(\__('The shipment to create a label for is missing.'));
        }
        if (!$this->authorization->isAllowed('Polkurier_Shipping::shipment_create')) {
            throw new LocalizedException(\__('You are not allowed to place Polkurier orders.'));
        }
        $order = $magentoShipment->getOrder();

        $shipment = $magentoShipment->getId() ? $this->findLinked((int)$magentoShipment->getId()) : null;
        if ($shipment === null) {
            $draft = $this->buildDraft($magentoShipment, $order, (array)$request->getData('packages'));
            $shipment = $this->placing->forMagentoShipment(
                (int)$order->getEntityId(),
                $draft,
                $magentoShipment->getId() ? (int)$magentoShipment->getId() : null,
            );
            $magentoShipment->setData(self::LINK, (int)$shipment->getId());
        }
        // Magento saves it once the label is done: with the number the order was sent with.
        if ($shipment->getReservedShipmentNumber() !== null && !$magentoShipment->getIncrementId()) {
            $magentoShipment->setIncrementId($shipment->getReservedShipmentNumber());
        }

        return $this->answer($magentoShipment, $order, $shipment);
    }

    /**
     * @param array<int|string, mixed> $packages
     */
    private function buildDraft(MagentoShipmentModel $magentoShipment, Order $order, array $packages): ShipmentDraftInterface
    {
        $qtys = [];
        foreach ($magentoShipment->getAllItems() as $item) {
            $qtys[(int)$item->getOrderItemId()] = (float)$item->getQty();
        }
        $data = $this->hydrator->toArray($this->draftBuilder->createFromOrder($order, $qtys));
        $options = $this->request->getParam(self::OPTIONS_PARAM);
        if (\is_array($options)) {
            $data = \array_replace($data, \array_intersect_key($options, \array_flip(self::OPTION_FIELDS)));
        }
        $draft = $this->hydrator->fromArray($data);
        $this->assertPackedOnce($packages, $qtys);
        $packs = $this->toPacks($packages);
        if ($packs !== []) {
            $draft->setPacks($packs);
        }

        return $draft;
    }

    /**
     * No item is shipped twice: the packages hold no more of an item than the shipment carries,
     * and nothing it doesn't. Magento's packaging window keeps to that; this checks what's posted.
     *
     * @param array<int|string, mixed> $packages
     * @param array<int, float> $qtys what the shipment carries, by order item id
     *
     * @throws LocalizedException
     */
    private function assertPackedOnce(array $packages, array $qtys): void
    {
        $packed = [];
        foreach ($packages as $package) {
            foreach (\is_array($package) ? (array)($package['items'] ?? []) : [] as $key => $item) {
                $orderItemId = (int)(\is_array($item) && isset($item['order_item_id']) ? $item['order_item_id'] : $key);
                $packed[$orderItemId] = ($packed[$orderItemId] ?? 0.0) + (float)(\is_array($item) ? ($item['qty'] ?? 0) : 0);
            }
        }
        foreach ($packed as $orderItemId => $qty) {
            if ($qty > 0 && !isset($qtys[$orderItemId])) {
                throw new LocalizedException(\__('A package holds an item the shipment doesn\'t carry.'));
            }
            if ($qty > ($qtys[$orderItemId] ?? 0.0) + 0.0001) {
                throw new LocalizedException(\__('The packages hold more of an item than the shipment carries.'));
            }
        }
    }

    /**
     * @param array<int|string, mixed> $packages
     *
     * @return Pack[]
     */
    private function toPacks(array $packages): array
    {
        $packs = [];
        foreach ($packages as $package) {
            $params = \is_array($package) ? (array)($package['params'] ?? []) : [];
            $units = (string)($params['dimension_units'] ?? '');
            // What it holds, as the packaging window put it in.
            $items = [];
            foreach (\is_array($package) ? (array)($package['items'] ?? []) : [] as $key => $item) {
                $qty = (float)(\is_array($item) ? ($item['qty'] ?? 0) : 0);
                if ($qty > 0) {
                    $items[] = $this->itemFactory->create()
                        ->setOrderItemId((int)(\is_array($item) && isset($item['order_item_id']) ? $item['order_item_id'] : $key))
                        ->setQty($qty);
                }
            }
            $packs[] = $this->packFactory->create()
                ->setItems($items)
                ->setTemplateId(null)
                ->setPackType(PackKinds::normalize((string)($params['container'] ?? '')))
                ->setLength($this->centimetres((float)($params['length'] ?? 0), $units))
                ->setWidth($this->centimetres((float)($params['width'] ?? 0), $units))
                ->setHeight($this->centimetres((float)($params['height'] ?? 0), $units))
                ->setWeight(\max(0.1, $this->kilograms((float)($params['weight'] ?? 0), (string)($params['weight_units'] ?? ''))))
                ->setAmount(1)
                // Saved as a pack template once placed, as asked in the packaging window.
                ->setKeepAs(\trim((string)($params[self::KEEP_AS_PARAM] ?? '')));
        }

        return $packs;
    }

    private function kilograms(float $weight, string $units): float
    {
        return \round($units === Weight::POUND ? $weight * StoreWeightUnit::KG_PER_POUND : $weight, 3);
    }

    private function centimetres(float $length, string $units): int
    {
        return (int)\ceil(\round($units === Length::INCH ? $length * self::CM_PER_INCH : $length, 2));
    }

    /**
     * Magento adds a track per waybill that comes with a label. Without a label it adds none, so
     * the tracks are added here, and the shipment is marked to be saved without a label.
     */
    private function answer(MagentoShipmentModel $magentoShipment, Order $order, ShipmentInterface $shipment): DataObject
    {
        $magentoShipment->setData(self::CARRIER, $shipment->getCarrierCode());
        $waybills = $shipment->getWaybills();
        $label = $waybills === [] ? null : $this->fetchLabel($shipment);
        if ($label !== null) {
            return new DataObject(['info' => [['tracking_number' => $waybills, 'label_content' => $label]]]);
        }

        $known = [];
        foreach ((array)$magentoShipment->getTracks() as $track) {
            $known[] = (string)$track->getTrackNumber();
        }
        foreach (\array_diff($waybills, $known) as $waybill) {
            $track = $this->trackFactory->create();
            $track->setNumber($waybill);
            $track->setCarrierCode(Polkurier::CODE);
            $track->setTitle($this->magentoShipment->getTrackTitle($order, $shipment->getCarrierCode()));
            $magentoShipment->addTrack($track);
        }
        $magentoShipment->setData(self::NO_LABEL, true);
        // Told in Magento's own message about the shipment (Plugin\Adminhtml\ShipmentSavePlugin).
        $this->unreadyLabel = (string)$shipment->getPolkurierNumber();

        return new DataObject(['info' => []]);
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

    private function fetchLabel(ShipmentInterface $shipment): ?string
    {
        try {
            $content = $this->documents->getLabels([$shipment], true)['content'];
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
     * The store of the order on the shipment pages, where Magento asks for container types
     * without saying which order they are for.
     */
    private function getContextStoreId(): ?int
    {
        try {
            $orderId = (int)$this->request->getParam('order_id');
            if ($orderId) {
                return (int)$this->orderRepository->get($orderId)->getStoreId();
            }
            $shipmentId = (int)$this->request->getParam('shipment_id');
            if ($shipmentId) {
                return (int)$this->magentoShipmentRepository->get($shipmentId)->getStoreId();
            }
        } catch (NoSuchEntityException) {
            return null;
        }

        return null;
    }
}
