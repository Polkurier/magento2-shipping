<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Placement;

use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Polkurier\Shipping\Api\Data\AddressInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Address\AddressHandler;
use Polkurier\Shipping\Model\Admin\FlashMessageCollector;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\BankAccount\BankAccountHandler;
use Polkurier\Shipping\Model\BankAccount\BankAccountMapper;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\PackTemplate\PackTemplateHandler;
use Polkurier\Shipping\Model\ResourceModel\Address\CollectionFactory as AddressCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\BankAccount\CollectionFactory as BankAccountCollectionFactory;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory as PackTemplateCollectionFactory;
use Polkurier\Shipping\Model\Sync\EntityHandlerInterface;
use Polkurier\Shipping\Model\Sync\SyncResult;

/**
 * Saves what was typed in for a shipment rather than chosen, when asked to: the sender to the
 * addresses, the cash on delivery account to the bank accounts, and parcels without a template to
 * the pack templates. They're saved as their settings pages save them, and so sent to Polkurier.
 * This happens only once the shipment is placed: a refused one keeps nothing. The first of each
 * becomes the default.
 *
 * The shipment is placed whatever becomes of them. What became of each is gathered for the action
 * that placed the shipment, to tell the admin (takeOutcome()). That action also checks whether the
 * admin may change them: it drops what they may not keep before the draft gets here.
 */
class ManualEntrySaver implements ResetAfterRequestInterface
{
    private FlashMessageCollector $flashMessageCollector;

    public function __construct(
        private readonly AddressHandler $addressHandler,
        private readonly BankAccountHandler $bankAccountHandler,
        private readonly PackTemplateHandler $packTemplateHandler,
        private readonly AddressCollectionFactory $addressCollectionFactory,
        private readonly BankAccountCollectionFactory $bankAccountCollectionFactory,
        private readonly PackTemplateCollectionFactory $packTemplateCollectionFactory,
        private readonly Logger $logger,
        private readonly FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        $this->flashMessageCollector = $this->flashMessageCollectorFactory->create();
    }

    public function keep(ShipmentDraftInterface $draft): void
    {
        if ($draft->isNewSender() && $draft->isKeepingNewSender()) {
            $address = $draft->getNewSenderAddress();
            $this->save($this->addressHandler, [
                AddressInterface::FIELD_NAME => $draft->getNewSenderName() ?: self::addressName($address),
                AddressInterface::FIELD_TYPE => AddressInterface::TYPE_SENDER,
                AddressInterface::FIELD_COUNTRY => Polkurier::HOME_COUNTRY,
                AddressInterface::FIELD_IS_DEFAULT => $this->isFirst($this->addressCollectionFactory->create()),
                // The drop-off point used this time, for the next shipment from this address.
                AddressInterface::FIELD_POINT_ID => $draft->getSenderPointId(),
            ] + \array_filter($address, static fn(string $value): bool => $value !== ''));
        }
        if ($draft->isNewBankAccount() && $draft->isKeepingNewBankAccount()
            && $draft->getNewBankAccountNumber() !== ''
        ) {
            $this->save($this->bankAccountHandler, [
                BankAccountInterface::FIELD_NAME => self::bankAccountName($draft),
                BankAccountInterface::FIELD_NUMBER => BankAccountMapper::normalizeNumber(
                    $draft->getNewBankAccountNumber(),
                ),
                BankAccountInterface::FIELD_IS_DEFAULT => $this->isFirst($this->bankAccountCollectionFactory->create()),
            ]);
        }
        foreach ($draft->getPacks() as $pack) {
            if ($pack->getKeepAs() === '' || $pack->getTemplateId() !== null) {
                continue;
            }
            $this->save($this->packTemplateHandler, [
                PackTemplateInterface::FIELD_NAME => $pack->getKeepAs(),
                PackTemplateInterface::FIELD_PACK_TYPE => $pack->getPackType(),
                PackTemplateInterface::FIELD_LENGTH => $pack->getLength(),
                PackTemplateInterface::FIELD_WIDTH => $pack->getWidth(),
                PackTemplateInterface::FIELD_HEIGHT => $pack->getHeight(),
                PackTemplateInterface::FIELD_WEIGHT => $pack->getWeight(),
                PackTemplateInterface::FIELD_IS_DEFAULT => $this->isFirst(
                    $this->packTemplateCollectionFactory->create(),
                ),
            ]);
        }
    }

    /**
     * What became of the entries kept since the last call, as one message to send; empty when
     * nothing was kept.
     */
    public function takeOutcome(): FlashMessageCollector
    {
        $outcome = $this->flashMessageCollector;
        $this->flashMessageCollector = $this->flashMessageCollectorFactory->create();

        return $outcome;
    }

    public function _resetState(): void
    {
        $this->flashMessageCollector = $this->flashMessageCollectorFactory->create();
    }

    /**
     * What an address typed in is called when no name was given: "Company, City".
     *
     * @param array<string, string> $address
     */
    public static function addressName(array $address): string
    {
        return \implode(', ', \array_filter([
            ($address[AddressInterface::FIELD_COMPANY] ?? '') ?: ($address[AddressInterface::FIELD_PERSON] ?? ''),
            $address[AddressInterface::FIELD_CITY] ?? '',
        ]));
    }

    /**
     * What a bank account typed in is called when no name was given: "Account ending 1234".
     */
    public static function bankAccountName(ShipmentDraftInterface $draft): string
    {
        $number = BankAccountMapper::normalizeNumber($draft->getNewBankAccountNumber());

        return $draft->getNewBankAccountName() ?: (string)__('Account ending %1', \substr($number, -4));
    }

    /**
     * @param array<string, mixed> $data as the entry's settings page posts it
     */
    private function save(EntityHandlerInterface $handler, array $data): void
    {
        $label = \mb_strtolower($handler->getLabel());
        $name = (string)($data['name'] ?? '');
        try {
            $entity = $handler->create();
            $result = $handler->save($entity, $data);
        } catch (\Throwable $e) {
            $this->logger->warning(
                'Polkurier: an entry typed in for a shipment could not be saved',
                ['exception' => $e],
            );
            $this->flashMessageCollector->warning(__('"%1" wasn\'t saved to %2: %3', $name, $label, $e->getMessage()));

            return;
        }
        $this->tell($entity, $result, $name, $label);
    }

    private function tell(SyncableInterface $entity, SyncResult $result, string $name, string $label): void
    {
        if ($result->failed > 0 || $entity->getSyncError()) {
            $this->flashMessageCollector->warning(__(
                '"%1" was saved to %2 in Magento, but not in Polkurier: %3',
                $name,
                $label,
                $entity->getSyncError() ?: \implode(' ', \array_unique($result->errors)),
            ));
        } elseif ($result->unconfirmed > 0) {
            $this->flashMessageCollector->notice(__(
                '"%1" was saved to %2 in Magento. '
                . 'It goes to Polkurier once you confirm it there with the code or your security PIN.',
                $name,
                $label,
            ));
        } else {
            $this->flashMessageCollector->success(__('"%1" was saved to %2.', $name, $label));
        }
    }

    /**
     * Whether there's none yet, not counting those deleted: the first one is the default.
     */
    private function isFirst(AbstractDb $collection): bool
    {
        return $collection->addFieldToFilter(SyncableInterface::FIELD_DELETED_AT, ['null' => true])->getSize() === 0;
    }
}
