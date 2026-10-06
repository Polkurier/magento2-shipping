<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Message\ManagerInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\Data\SyncResultInterface;
use Polkurier\Shipping\Model\Admin\AddressHandler;
use Polkurier\Shipping\Model\Admin\BankAccountHandler;
use Polkurier\Shipping\Model\Admin\EntityHandlerInterface;
use Polkurier\Shipping\Model\Admin\PackTemplateHandler;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Mapper\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory as AddressCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\BankAccount\CollectionFactory as BankAccountCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory as PackTemplateCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Saves what a shipment had typed in rather than chosen, where asked to: the sender to the
 * addresses, the cash on delivery account to the bank accounts, and parcels without a template to
 * the pack templates, as their settings pages save them, and so sent to Polkurier. Only once the
 * shipment is placed: a refused one keeps nothing. The first of each becomes the default.
 *
 * The shipment is placed whatever becomes of them; each one's outcome is said in the admin.
 */
readonly class KeepForLater
{
    public function __construct(
        private AddressHandler $addresses,
        private BankAccountHandler $bankAccounts,
        private PackTemplateHandler $packTemplates,
        private AddressCollectionFactory $addressCollectionFactory,
        private BankAccountCollectionFactory $bankAccountCollectionFactory,
        private PackTemplateCollectionFactory $packTemplateCollectionFactory,
        private AuthorizationInterface $authorization,
        private ManagerInterface $messageManager,
        private LoggerInterface $logger,
    ) {}

    public function keep(ShipmentDraftInterface $draft): void
    {
        if ($draft->isNewSender() && $draft->isKeepingNewSender()) {
            $address = $draft->getNewSenderAddress();
            $this->save($this->addresses, 'Polkurier_Shipping::addresses', [
                AddressInterface::NAME => $draft->getNewSenderName() ?: self::addressName($address),
                AddressInterface::TYPE => AddressInterface::TYPE_SENDER,
                AddressInterface::COUNTRY => Polkurier::HOME_COUNTRY,
                AddressInterface::IS_DEFAULT => $this->isFirst($this->addressCollectionFactory->create()),
                // Handed over where it was this time, for the next shipment from it.
                AddressInterface::POINT_ID => $draft->getSenderPointId(),
            ] + \array_filter($address, static fn(string $value): bool => $value !== ''));
        }
        if ($draft->isNewBankAccount() && $draft->isKeepingNewBankAccount() && $draft->getNewBankAccountNumber() !== '') {
            $number = CreateOrderMessageMapper::accountNumber($draft->getNewBankAccountNumber());
            $this->save($this->bankAccounts, 'Polkurier_Shipping::bank_accounts', [
                'name' => $draft->getNewBankAccountName() ?: (string)\__('Account ending %1', \substr($number, -4)),
                'number' => $number,
                'is_default' => $this->isFirst($this->bankAccountCollectionFactory->create()),
            ]);
        }
        foreach ($draft->getPacks() as $pack) {
            if ($pack->getKeepAs() === '' || $pack->getTemplateId() !== null) {
                continue;
            }
            $this->save($this->packTemplates, 'Polkurier_Shipping::pack_templates', [
                'name' => $pack->getKeepAs(),
                'pack_type' => $pack->getPackType(),
                'length' => $pack->getLength(),
                'width' => $pack->getWidth(),
                'height' => $pack->getHeight(),
                'weight' => $pack->getWeight(),
                'is_default' => $this->isFirst($this->packTemplateCollectionFactory->create()),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $data as the entry's settings page posts it
     */
    private function save(EntityHandlerInterface $handler, string $resource, array $data): void
    {
        $label = \mb_strtolower($handler->getLabel());
        $name = (string)($data['name'] ?? '');
        if (!$this->authorization->isAllowed($resource)) {
            $this->messageManager->addWarningMessage((string)\__('"%1" wasn\'t saved to %2: you aren\'t allowed to change them.', $name, $label));

            return;
        }
        try {
            $entity = $handler->create();
            $result = $handler->save($entity, $data);
            $entity = $handler->get((int)$entity->getId());
        } catch (\Throwable $e) {
            $this->logger->warning('Polkurier: an entry typed in for a shipment could not be saved', ['exception' => $e]);
            $this->messageManager->addWarningMessage((string)\__('"%1" wasn\'t saved to %2: %3', $name, $label, $e->getMessage()));

            return;
        }
        $this->tell($entity, $result, $name, $label);
    }

    private function tell(SyncableInterface $entity, SyncResultInterface $result, string $name, string $label): void
    {
        if ($result->getFailed() > 0 || $entity->getSyncError()) {
            $this->messageManager->addWarningMessage((string)\__(
                '"%1" was saved to %2 in Magento, but not in Polkurier: %3',
                $name,
                $label,
                $entity->getSyncError() ?: \implode(' ', \array_unique($result->getErrors())),
            ));
        } elseif ($result->getUnconfirmed() > 0) {
            $this->messageManager->addNoticeMessage((string)\__(
                '"%1" was saved to %2 in Magento. It goes to Polkurier once you confirm it there with the code or your security PIN.',
                $name,
                $label,
            ));
        } else {
            $this->messageManager->addSuccessMessage((string)\__('"%1" was saved to %2.', $name, $label));
        }
    }

    /**
     * Whether there's none yet, not counting those deleted: the first one is the default.
     */
    private function isFirst(AbstractDb $collection): bool
    {
        return $collection->addFieldToFilter(SyncableInterface::DELETED_AT, ['null' => true])->getSize() === 0;
    }

    /**
     * What an address typed in is called when no name was given: "Company, City".
     *
     * @param array<string, string> $address
     */
    public static function addressName(array $address): string
    {
        return \implode(', ', \array_filter([
            ($address[AddressInterface::COMPANY] ?? '') ?: ($address[AddressInterface::PERSON] ?? ''),
            $address[AddressInterface::CITY] ?? '',
        ]));
    }
}
