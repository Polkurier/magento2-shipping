<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Provider\Shipment;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\Order\AddressInterface;
use Polkurier\Shipping\Api\Data\Order\CodInterface;
use Polkurier\Shipping\Api\Data\Order\CodInterfaceFactory;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Helper\AddressMapper;
use Polkurier\Shipping\Model\Order\AddressFactory;
use Polkurier\Shipping\Model\Provider\ConfigProvider;
use Polkurier\Webservice\Enum\CodReturnTypeEnum;
use Polkurier\Webservice\Enum\CodStatusEnum;

readonly class DefaultsProvider
{
    /**
     * @param AddressRepositoryInterface $addressRepository
     * @param AddressFactory $addressFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param ConfigProvider $configProvider
     * @param AddressMapper $addressMapper
     * @param PackTemplateRepositoryInterface $packTemplateRepository
     * @param CodInterfaceFactory $codFactory
     * @param BankAccountRepositoryInterface $bankAccountRepository
     */
    public function __construct(
        /**
         * @var AddressRepositoryInterface
         */
        private AddressRepositoryInterface $addressRepository,

        /**
         * @var AddressFactory
         */
        private AddressFactory $addressFactory,

        /**
         * @var ScopeConfigInterface
         */
        private ScopeConfigInterface $scopeConfig,

        /**
         * @var ConfigProvider
         */
        private ConfigProvider $configProvider,

        /**
         * @var AddressMapper
         */
        private AddressMapper $addressMapper,

        /**
         * @var PackTemplateRepositoryInterface
         */
        private PackTemplateRepositoryInterface $packTemplateRepository,

        /**
         * @var CodInterfaceFactory
         */
        private CodInterfaceFactory $codFactory,

        /**
         * @var BankAccountRepositoryInterface
         */
        private BankAccountRepositoryInterface $bankAccountRepository,
    ) {}

    /**
     * @param OrderInterface $order
     *
     * @return float
     */
    public function getDefaultInsuranceAmount(OrderInterface $order): float
    {
        return (float)$order?->getGrandTotal();
    }

    /**
     * @return PackTemplateInterface|null
     */
    public function getDefaultPackTemplate(): ?PackTemplateInterface
    {
        return $this->packTemplateRepository->findOneDefault();
    }

    /**
     * @return BankAccountInterface|null
     */
    public function getDefaultBankAccount(): ?BankAccountInterface
    {
        return $this->bankAccountRepository->findOneDefault();
    }

    /**
     * @param OrderInterface $order
     *
     * @return CodInterface
     */
    public function createDefaultCod(OrderInterface $order): CodInterface
    {
        $cod = $this->codFactory->create();
        $cod->setAmount($this->getDefaultCodAmount($order));
        $cod->setType($this->configProvider->getDefaultCodType());
        $cod->setReturnType($this->configProvider->getDefaultCodRefundType());
        $cod->setStatus(CodStatusEnum::PAYMENT_PENDING);
        if ($cod->getReturnType() === CodReturnTypeEnum::BANK_ACCOUNT
            && $defaultAccount = $this->getDefaultBankAccount()
        ) {
            $cod->setBankAccountId($defaultAccount->getAccountId());
            $cod->setBankAccount($defaultAccount->getNumber());
        }

        return $cod;
    }

    /**
     * @param OrderInterface $order
     *
     * @return float
     */
    public function getDefaultCodAmount(OrderInterface $order): float
    {
        return (float)$order->getGrandTotal();
    }

    /**
     * @return AddressInterface
     */
    public function getDefaultSender(): AddressInterface
    {
        $address = $this->addressRepository->findOneDefault();

        return match (true) {
            $address !== null => $this->addressMapper->mapConfigAddress($address),
            $this->configProvider->isUseStoreOriginAddress() => $this->buildAddressFromOriginAddress(),
            $this->configProvider->isUseStoreInformationAddress() => $this->buildAddressFromStoreInfoAddress(),
            default => $this->addressFactory->create(),
        };
    }

    /**
     * @return AddressInterface
     */
    private function buildAddressFromOriginAddress(): AddressInterface
    {
        $address = $this->addressFactory->create();
        $address->setPerson($this->getStoreInfo('name'));
        $address->setStreet($this->joinStreet(
            $this->getOrigin('street_line1'),
            $this->getOrigin('street_line2')
        ));
        $address->setCity($this->getOrigin('city'));
        $address->setPostcode($this->getOrigin('postcode'));
        $address->setCountry($this->getOrigin('country_id'));
        $address->setEmail($this->getEmail());
        $address->setPhone($this->getStoreInfo('phone'));

        return $address;
    }

    /**
     * @return AddressInterface
     */
    private function buildAddressFromStoreInfoAddress(): AddressInterface
    {
        $address = $this->addressFactory->create();
        $address->setPerson($this->getStoreInfo('name'));
        $address->setStreet($this->joinStreet(
            $this->getStoreInfo('street_line1'),
            $this->getStoreInfo('street_line2')
        ));
        $address->setCity($this->getStoreInfo('city'));
        $address->setPostcode($this->getStoreInfo('postcode'));
        $address->setCountry($this->getStoreInfo('country_id'));
        $address->setEmail($this->getEmail());
        $address->setPhone($this->getStoreInfo('phone'));

        return $address;
    }

    /**
     * @param string $line1
     * @param string $line2
     *
     * @return string
     */
    private function joinStreet(string $line1, string $line2): string
    {
        return $line1 . ($line2 ? "\n" . $line2 : '');
    }

    /**
     * @return string
     */
    private function getEmail(): string
    {
        return $this->scopeConfig->getValue(\sprintf(
            'trans_email/ident_%s/email',
            $this->configProvider->getDefaultSenderEmail()
        ));
    }

    /**
     * @param string $field
     *
     * @return string
     */
    private function getStoreInfo(string $field): string
    {
        return trim((string)$this->scopeConfig->getValue(\sprintf('general/store_information/%s', $field)));
    }

    /**
     * @param string $field
     *
     * @return string
     */
    private function getOrigin(string $field): string
    {
        return trim((string)$this->scopeConfig->getValue(\sprintf('shipping/origin/%s', $field)));
    }
}
