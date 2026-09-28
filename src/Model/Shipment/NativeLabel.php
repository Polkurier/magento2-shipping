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
use Magento\Framework\Measure\Length;
use Magento\Framework\Measure\Weight;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface as MagentoShipmentRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment as MagentoShipmentModel;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Config\Source\PackType;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\Draft\Pack;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;
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
     * Request parameter carrying the options set on the shipment page.
     */
    public const string OPTIONS_PARAM = 'polkurier';

    /**
     * Draft fields the shipment page may set. Recipient and parcels come from the order and the
     * packaging popup.
     */
    public const array OPTION_FIELDS = [
        ShipmentDraftInterface::CARRIER_CODE,
        ShipmentDraftInterface::SHIPMENT_TYPE,
        ShipmentDraftInterface::DESCRIPTION,
        ShipmentDraftInterface::SENDER_ADDRESS_ID,
        ShipmentDraftInterface::RECIPIENT_POINT_ID,
        ShipmentDraftInterface::COD_AMOUNT,
        ShipmentDraftInterface::COD_TYPE,
        ShipmentDraftInterface::COD_RETURN_TYPE,
        ShipmentDraftInterface::COD_BANK_ACCOUNT_ID,
        ShipmentDraftInterface::INSURANCE_AMOUNT,
        ShipmentDraftInterface::SERVICES,
        ShipmentDraftInterface::PICKUP_MODE,
        ShipmentDraftInterface::PICKUP_DATE,
        ShipmentDraftInterface::PICKUP_TIME_FROM,
        ShipmentDraftInterface::PICKUP_TIME_TO,
    ];

    private const float KG_PER_POUND = 0.45359237;
    private const float CM_PER_INCH = 2.54;

    public function __construct(
        private readonly Config $config,
        private readonly AuthorizationInterface $authorization,
        private readonly RequestInterface $request,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly MagentoShipmentRepository $magentoShipmentRepository,
        private readonly ShipmentManagement $shipmentManagement,
        private readonly DefaultDraftBuilder $draftBuilder,
        private readonly DraftHydrator $hydrator,
        private readonly PackFactory $packFactory,
        private readonly PackType $packTypes,
        private readonly CollectionFactory $shipmentCollectionFactory,
        private readonly Documents $documents,
        private readonly MagentoShipment $magentoShipment,
        private readonly TrackFactory $trackFactory,
        private readonly ManagerInterface $messageManager,
        private readonly LoggerInterface $logger,
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
            $draft = $this->buildDraft($order, (array)$request->getData('packages'));
            $shipment = $this->shipmentManagement->placeForMagentoShipment((int)$order->getEntityId(), $draft);
            $magentoShipment->setData(self::LINK, (int)$shipment->getId());
        }

        return $this->answer($magentoShipment, $order, $shipment);
    }

    /**
     * @param array<int|string, mixed> $packages
     */
    private function buildDraft(Order $order, array $packages): ShipmentDraftInterface
    {
        $data = $this->hydrator->toArray($this->draftBuilder->createFromOrder($order));
        $options = $this->request->getParam(self::OPTIONS_PARAM);
        if (\is_array($options)) {
            $data = \array_replace($data, \array_intersect_key($options, \array_flip(self::OPTION_FIELDS)));
        }
        $draft = $this->hydrator->fromArray($data);
        $packs = $this->toPacks($packages);
        if ($packs !== []) {
            $draft->setPacks($packs);
        }

        return $draft;
    }

    /**
     * @param array<int|string, mixed> $packages
     * @return Pack[]
     */
    private function toPacks(array $packages): array
    {
        $packs = [];
        foreach ($packages as $package) {
            $params = \is_array($package) ? (array)($package['params'] ?? []) : [];
            $units = (string)($params['dimension_units'] ?? '');
            $type = PackTypeEnum::tryFrom((string)($params['container'] ?? '')) ?? PackTypeEnum::Standard;
            $packs[] = $this->packFactory->create()
                ->setTemplateId(null)
                ->setPackType($type->value)
                ->setLength($this->centimetres((float)($params['length'] ?? 0), $units))
                ->setWidth($this->centimetres((float)($params['width'] ?? 0), $units))
                ->setHeight($this->centimetres((float)($params['height'] ?? 0), $units))
                ->setWeight(\max(0.1, $this->kilograms((float)($params['weight'] ?? 0), (string)($params['weight_units'] ?? ''))))
                ->setAmount(1);
        }

        return $packs;
    }

    private function kilograms(float $weight, string $units): float
    {
        return \round($units === Weight::POUND ? $weight * self::KG_PER_POUND : $weight, 3);
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
        $this->messageManager->addNoticeMessage(\__(
            'Polkurier order %1 is placed, but its label is not ready yet. Print it later from the Polkurier shipments of the order.',
            (string)$shipment->getPolkurierNumber(),
        ));

        return new DataObject(['info' => []]);
    }

    private function fetchLabel(ShipmentInterface $shipment): ?string
    {
        try {
            $content = $this->documents->getLabels([$shipment], true)['content'];
        } catch (LocalizedException $e) {
            $this->logger->warning('Polkurier label not available for the Magento shipment', [
                'polkurier_number' => $shipment->getPolkurierNumber(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return \str_contains($content, '%PDF-') ? $content : null;
    }

    private function findLinked(int $magentoShipmentId): ?ShipmentInterface
    {
        $collection = $this->shipmentCollectionFactory->create()
            ->addFieldToFilter(ShipmentInterface::SHIPMENT_ID, ['eq' => $magentoShipmentId])
            ->addFieldToFilter(ShipmentInterface::STATE, ['eq' => ShipmentInterface::STATE_CREATED])
            ->setOrder(ShipmentInterface::ENTITY_ID, 'DESC')
            ->setPageSize(1);
        $shipment = $collection->getFirstItem();

        return $shipment instanceof ShipmentInterface && $shipment->getId() ? $shipment : null;
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
        } catch (\Exception) {
            return null;
        }

        return null;
    }
}
