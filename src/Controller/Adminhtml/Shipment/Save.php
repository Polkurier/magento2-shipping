<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentDraftInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Controller\Adminhtml\Address\Save as AddressSave;
use Polkurier\Shipping\Controller\Adminhtml\Bankaccount\Save as BankAccountSave;
use Polkurier\Shipping\Controller\Adminhtml\Packtemplate\Save as PackTemplateSave;
use Polkurier\Shipping\Controller\Adminhtml\ReadsPostDataTrait;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator;
use Polkurier\Shipping\Model\Shipment\Placement\ManualEntrySaver;
use Polkurier\Shipping\Model\Shipment\Placement\RejectedDraftStorage;

/**
 * Places the Polkurier order from the shipment form. Entries typed in to be kept as addresses, bank
 * accounts or pack templates are kept only if the admin is allowed to change that list.
 */
class Save extends Action implements HttpPostActionInterface
{
    use ReadsPostDataTrait;

    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipment_create';

    public function __construct(
        Context $context,
        private readonly ShipmentManagementInterface $shipmentManagement,
        private readonly DraftHydrator $draftHydrator,
        private readonly RejectedDraftStorage $rejectedDraftStorage,
        private readonly ManualEntrySaver $manualEntrySaver,
        private readonly FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $data = $this->getPostData();
        $orderId = (int)($data['order_id'] ?? 0);
        $redirect = $this->resultRedirectFactory->create();

        try {
            $shipment = $this->shipmentManagement->create(
                $orderId,
                $this->keepingOnlyAllowed($this->draftHydrator->fromArray($data)),
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $this->rejectedDraftStorage->keep($data);

            return $redirect->setPath('*/*/create', ['order_id' => $orderId]);
        }

        if ($shipment->getState() === ShipmentInterface::STATE_UNKNOWN) {
            $this->messageManager->addWarningMessage(__(
                'Polkurier did not confirm the order, so it may or may not have been placed. '
                . 'It will not be sent again until it is reconciled: '
                . 'use "Reconcile" in the Polkurier section of the order. (%1)',
                (string)$shipment->getLastError(),
            ));
        } else {
            // Placed, with what went wrong after, if anything (the Magento shipment, say), in one message.
            $this->flashMessageCollectorFactory->create()
                ->success(__(
                    'Polkurier order %1 placed. Waybills: %2.',
                    $shipment->getPolkurierNumber(),
                    \implode(', ', $shipment->getWaybills()) ?: (string)__('not issued yet'),
                ))
                ->warning((string)$shipment->getLastError())
                ->send($this->messageManager);
        }

        // Once it's placed, report what became of the entries typed in to be kept.
        $this->manualEntrySaver->takeOutcome()->send($this->messageManager);

        // To the placed shipment's page, whatever the outcome.
        return $redirect->setPath('*/*/view', ['id' => $shipment->getId()]);
    }

    /**
     * The draft, no longer asking to keep what the admin may not change; a warning says so instead.
     */
    private function keepingOnlyAllowed(ShipmentDraftInterface $draft): ShipmentDraftInterface
    {
        if ($draft->isNewSender()
            && $draft->isKeepingNewSender()
            && !$this->_authorization->isAllowed(AddressSave::ADMIN_RESOURCE)
        ) {
            $draft->setKeepingNewSender(false);
            $this->notAllowed(
                $draft->getNewSenderName() ?: ManualEntrySaver::addressName($draft->getNewSenderAddress()),
                __('Addresses'),
            );
        }
        if ($draft->isNewBankAccount()
            && $draft->isKeepingNewBankAccount()
            && !$this->_authorization->isAllowed(BankAccountSave::ADMIN_RESOURCE)
        ) {
            $draft->setKeepingNewBankAccount(false);
            $this->notAllowed(ManualEntrySaver::bankAccountName($draft), __('Bank accounts'));
        }
        if (!$this->_authorization->isAllowed(PackTemplateSave::ADMIN_RESOURCE)) {
            foreach ($draft->getPacks() as $pack) {
                if ($pack->getKeepAs() !== '' && $pack->getTemplateId() === null) {
                    $this->notAllowed($pack->getKeepAs(), __('Pack templates'));
                    $pack->setKeepAs('');
                }
            }
        }

        return $draft;
    }

    private function notAllowed(string $name, \Stringable $label): void
    {
        $this->messageManager->addWarningMessage(__(
            '"%1" wasn\'t saved to %2: you aren\'t allowed to change them.',
            $name,
            \mb_strtolower((string)$label),
        ));
    }
}
