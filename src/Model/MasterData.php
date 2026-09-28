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
 * The address book, bank accounts and pack templates of a website, as the shipment form uses them.
 * Entries deleted in Magento but not yet in Polkurier are left out.
 */
class MasterData
{
    public function __construct(
        private readonly AddressCollectionFactory $addressCollectionFactory,
        private readonly BankAccountCollectionFactory $bankAccountCollectionFactory,
        private readonly PackTemplateCollectionFactory $packTemplateCollectionFactory,
    ) {}

    /**
     * @return AddressInterface[]
     */
    public function getSenderAddresses(int $websiteId, bool $coverOnly = false): array
    {
        $collection = $this->addressCollectionFactory->create()
            ->addFieldToFilter(SyncableInterface::WEBSITE_ID, ['eq' => $websiteId])
            ->addFieldToFilter(SyncableInterface::DELETED_AT, ['null' => true])
            ->addFieldToFilter(AddressInterface::TYPE, AddressInterface::TYPE_SENDER)
            ->setOrder(AddressInterface::IS_DEFAULT, 'DESC')
            ->setOrder(AddressInterface::NAME, 'ASC');
        if ($coverOnly) {
            $collection->addFieldToFilter(AddressInterface::IS_COVER, ['eq' => 1]);
        }

        return \array_values($collection->getItems());
    }

    public function getDefaultSenderAddress(int $websiteId): ?AddressInterface
    {
        foreach ($this->getSenderAddresses($websiteId) as $address) {
            if ($address->isDefault()) {
                return $address;
            }
        }

        return null;
    }

    /**
     * @return BankAccountInterface[]
     */
    public function getBankAccounts(int $websiteId): array
    {
        return \array_values($this->bankAccountCollectionFactory->create()
            ->addFieldToFilter(SyncableInterface::WEBSITE_ID, ['eq' => $websiteId])
            ->addFieldToFilter(SyncableInterface::DELETED_AT, ['null' => true])
            ->setOrder(BankAccountInterface::IS_DEFAULT, 'DESC')
            ->setOrder(BankAccountInterface::NAME, 'ASC')
            ->getItems());
    }

    public function getDefaultBankAccount(int $websiteId): ?BankAccountInterface
    {
        return $this->getBankAccounts($websiteId)[0] ?? null;
    }

    /**
     * @return PackTemplateInterface[]
     */
    public function getPackTemplates(int $websiteId): array
    {
        return \array_values($this->packTemplateCollectionFactory->create()
            ->addFieldToFilter(SyncableInterface::WEBSITE_ID, ['eq' => $websiteId])
            ->addFieldToFilter(SyncableInterface::DELETED_AT, ['null' => true])
            ->setOrder(PackTemplateInterface::IS_DEFAULT, 'DESC')
            ->setOrder(PackTemplateInterface::NAME, 'ASC')
            ->getItems());
    }

    public function getDefaultPackTemplate(int $websiteId): ?PackTemplateInterface
    {
        foreach ($this->getPackTemplates($websiteId) as $template) {
            if ($template->isDefault()) {
                return $template;
            }
        }

        return null;
    }
}
