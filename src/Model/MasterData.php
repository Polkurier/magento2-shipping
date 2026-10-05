<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory as AddressCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\BankAccount\CollectionFactory as BankAccountCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory as PackTemplateCollectionFactory;

/**
 * The address book, bank accounts and pack templates, as the shipment form uses them.
 * Entries deleted in Magento but not yet in Polkurier are left out.
 */
readonly class MasterData
{
    public function __construct(
        private AddressCollectionFactory $addressCollectionFactory,
        private BankAccountCollectionFactory $bankAccountCollectionFactory,
        private PackTemplateCollectionFactory $packTemplateCollectionFactory,
    ) {}

    /**
     * @return AddressInterface[]
     */
    public function getSenderAddresses(bool $coverOnly = false): array
    {
        $collection = $this->addressCollectionFactory->create()
            ->addFieldToFilter(SyncableInterface::DELETED_AT, ['null' => true])
            ->addFieldToFilter(AddressInterface::TYPE, AddressInterface::TYPE_SENDER)
            ->setOrder(AddressInterface::IS_DEFAULT, 'DESC')
            ->setOrder(AddressInterface::NAME, 'ASC');
        if ($coverOnly) {
            $collection->addFieldToFilter(AddressInterface::IS_COVER, ['eq' => 1]);
        }

        return \array_values($collection->getItems());
    }

    public function getDefaultSenderAddress(): ?AddressInterface
    {
        foreach ($this->getSenderAddresses() as $address) {
            if ($address->isDefault()) {
                return $address;
            }
        }

        return null;
    }

    /**
     * @return BankAccountInterface[]
     */
    public function getBankAccounts(): array
    {
        return \array_values($this->bankAccountCollectionFactory->create()
            ->addFieldToFilter(SyncableInterface::DELETED_AT, ['null' => true])
            ->setOrder(BankAccountInterface::IS_DEFAULT, 'DESC')
            ->setOrder(BankAccountInterface::NAME, 'ASC')
            ->getItems());
    }

    /**
     * The account cash on delivery is paid out to unless the merchant picks another: the default
     * one, or else the first by name, since a shipment with cash on delivery needs an account.
     * Unlike the other defaults, it's null only when there is no account at all.
     */
    public function getPreferredBankAccount(): ?BankAccountInterface
    {
        return $this->getBankAccounts()[0] ?? null;
    }

    /**
     * @return PackTemplateInterface[]
     */
    public function getPackTemplates(): array
    {
        return \array_values($this->packTemplateCollectionFactory->create()
            ->addFieldToFilter(SyncableInterface::DELETED_AT, ['null' => true])
            ->setOrder(PackTemplateInterface::IS_DEFAULT, 'DESC')
            ->setOrder(PackTemplateInterface::NAME, 'ASC')
            ->getItems());
    }

    public function getDefaultPackTemplate(): ?PackTemplateInterface
    {
        foreach ($this->getPackTemplates() as $template) {
            if ($template->isDefault()) {
                return $template;
            }
        }

        return null;
    }
}
