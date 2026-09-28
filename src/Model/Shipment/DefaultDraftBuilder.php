<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Sales\Model\Order;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatures;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Shipping\Model\Mapper\StreetParser;
use Polkurier\Shipping\Model\OrderPointStore;
use Polkurier\Shipping\Model\Shipment\Draft\PackFactory;
use Polkurier\Shipping\Model\WebsiteScope;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Response\Carrier;
use Psr\Log\LoggerInterface;

/**
 * Prepares the draft a merchant starts from: what the customer ordered, where to, and the defaults
 * configured for the website, narrowed to what the carrier supports.
 */
class DefaultDraftBuilder
{
    public function __construct(
        private readonly DraftFactory $draftFactory,
        private readonly PackFactory $packFactory,
        private readonly Config $config,
        private readonly MasterData $masterData,
        private readonly OrderPointStore $orderPointStore,
        private readonly StreetParser $streetParser,
        private readonly CarrierCatalog $catalog,
        private readonly CarrierFeatures $features,
        private readonly WebsiteScope $websiteScope,
        private readonly LoggerInterface $logger,
    ) {}

    public function createFromOrder(Order $order): ShipmentDraftInterface
    {
        $storeId = (int)$order->getStoreId();
        $websiteId = $this->websiteScope->getWebsiteId($storeId);
        $carrierCode = $this->getCarrierCode($order, $storeId);
        $carrier = $carrierCode !== null ? $this->getCarrier($carrierCode, $storeId) : null;

        $draft = $this->draftFactory->create();
        $draft->setCarrierCode((string)$carrierCode);
        $draft->setShipmentType($this->getShipmentType($carrier, $storeId));
        $draft->setDescription(\sprintf($this->config->getDescriptionFormat($storeId), $order->getIncrementId()));
        $senderId = $this->masterData->getDefaultSenderAddress($websiteId)?->getId();
        $draft->setSenderAddressId($senderId !== null ? (int)$senderId : null);
        $this->setRecipient($draft, $order);
        $draft->setPacks([$this->createPack($order, $websiteId)]);
        $draft->setServices([]);
        $draft->setAdditionalFields([]);

        $grandTotal = \round((float)$order->getGrandTotal(), 2);
        $cod = \in_array((string)$order->getPayment()?->getMethod(), $this->config->getCodPaymentMethods($storeId), true);
        if ($cod) {
            $draft->setCodAmount($grandTotal);
            $draft->setCodType($this->config->getDefaultCodType($storeId)->value);
            $draft->setCodReturnType($this->config->getDefaultCodReturnType($storeId)->value);
            $bankAccountId = $this->masterData->getDefaultBankAccount($websiteId)?->getId();
            $draft->setCodBankAccountId($bankAccountId !== null ? (int)$bankAccountId : null);
        }
        $insuranceRequired = $cod && $carrier !== null && $this->features->isInsuranceRequiredWithCod($carrier);
        if ($this->config->isInsureByDefault($storeId) || $insuranceRequired) {
            $draft->setInsuranceAmount($grandTotal);
        }

        $offered = $carrier !== null ? $this->features->getServices($carrier) : [];
        $draft->setServices(\array_values(\array_map(
            static fn($service): string => $service->value,
            \array_filter(
                $this->config->getDefaultServices($storeId),
                static fn($service): bool => \in_array($service, $offered, true),
            ),
        )));

        $draft->setPickupMode(
            $carrier !== null && $this->features->isDropOffAvailable($carrier) && $this->features->isDropOffDefault($carrier)
                ? ShipmentDraftInterface::PICKUP_DROP_OFF
                : ShipmentDraftInterface::PICKUP_COURIER,
        );

        return $draft;
    }

    /**
     * The method the customer chose, or the configured default when the order was placed with
     * another shipping method.
     */
    private function getCarrierCode(Order $order, int $storeId): ?string
    {
        $method = (string)$order->getShippingMethod();
        $prefix = Polkurier::CODE . '_';
        if (\str_starts_with($method, $prefix)) {
            return \substr($method, \strlen($prefix));
        }

        return $this->config->getDefaultCarrier($storeId);
    }

    private function getCarrier(string $code, int $storeId): ?Carrier
    {
        try {
            return $this->catalog->getCarrier($code, $storeId);
        } catch (PolkurierException $e) {
            $this->logger->warning('Polkurier carrier list unavailable for the shipment draft', ['exception' => $e]);

            return null;
        }
    }

    private function getShipmentType(?Carrier $carrier, int $storeId): string
    {
        $default = $this->config->getDefaultShipmentType($storeId);
        if ($carrier === null) {
            return $default->value;
        }
        $types = $this->features->getShipmentTypes($carrier);

        return \in_array($default, $types, true) || $types === [] ? $default->value : $types[0]->value;
    }

    private function setRecipient(ShipmentDraftInterface $draft, Order $order): void
    {
        $address = $order->getShippingAddress() ?? $order->getBillingAddress();
        if ($address === null) {
            return;
        }
        $street = $this->streetParser->parse((array)$address->getStreet());
        $draft->setRecipientPerson(\trim($address->getFirstname() . ' ' . $address->getLastname()));
        $draft->setRecipientCompany($address->getCompany() ?: null);
        $draft->setRecipientStreet($street['street']);
        $draft->setRecipientHouseNumber($street['house_number']);
        $draft->setRecipientFlatNumber($street['flat_number']);
        $draft->setRecipientPostcode((string)$address->getPostcode());
        $draft->setRecipientCity((string)$address->getCity());
        $draft->setRecipientCountry((string)($address->getCountryId() ?: 'PL'));
        $draft->setRecipientPhone((string)$address->getTelephone());
        $draft->setRecipientEmail((string)($address->getEmail() ?: $order->getCustomerEmail()));
        $draft->setRecipientPointId($this->orderPointStore->getForOrder((int)$order->getEntityId())?->getPointId());
    }

    private function createPack(Order $order, int $websiteId): Draft\Pack
    {
        $pack = $this->packFactory->create();
        $template = $this->masterData->getDefaultPackTemplate($websiteId);
        if ($template !== null) {
            return $pack->setTemplateId((int)$template->getId())
                ->setPackType($template->getPackType())
                ->setLength($template->getLength())
                ->setWidth($template->getWidth())
                ->setHeight($template->getHeight())
                ->setWeight($template->getWeight())
                ->setAmount(1);
        }

        return $pack->setTemplateId(null)
            ->setPackType(PackTypeEnum::Standard->value)
            ->setLength(0)
            ->setWidth(0)
            ->setHeight(0)
            ->setWeight(\max(0.1, \round((float)$order->getWeight(), 3)))
            ->setAmount(1);
    }
}
