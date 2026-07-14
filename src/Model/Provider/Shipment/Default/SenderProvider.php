<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Provider\Shipment\Default;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Polkurier\Shipping\Api\AddressRepositoryInterface;
use Polkurier\Shipping\Api\Data\Order\AddressInterface;
use Polkurier\Shipping\Helper\AddressMapper;
use Polkurier\Shipping\Model\Order\AddressFactory;
use Polkurier\Shipping\Model\Provider\ConfigProvider;

readonly class SenderProvider
{
    /**
     * @param AddressRepositoryInterface $addressRepository
     * @param AddressFactory $addressFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param ConfigProvider $configProvider
     * @param AddressMapper $addressMapper
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
    ) {}

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
