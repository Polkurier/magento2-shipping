<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Framework\Message\ManagerInterface;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;

/**
 * On Magento's shipment page, a shipment with a label reports itself in Magento's own message.
 * When Polkurier had no label ready yet, that message is replaced by one that says so.
 *
 * A shipment saved without a label is Magento's alone, for an order of any carrier: one of a
 * Polkurier order gets its Polkurier order later, from its own page.
 */
readonly class ShipmentSavePlugin
{
    public function __construct(
        private ManagerInterface $messageManager,
        private NativeLabelService $nativeLabelService,
    ) {}

    public function afterExecute(Save $subject, mixed $result): mixed
    {
        $this->tellUnreadyLabel();

        return $result;
    }

    /**
     * Placed, but with no label yet, while Magento's message says it created one. That message is
     * replaced, so there's only one message and it's correct.
     */
    private function tellUnreadyLabel(): void
    {
        $number = $this->nativeLabelService->takeUnreadyLabel();
        if ($number === null) {
            return;
        }
        $created = (string)__('The shipment has been created.');
        foreach ($this->messageManager->getMessages(true)->getItems() as $message) {
            if (!\str_starts_with((string)$message->getText(), $created)) {
                $this->messageManager->addMessage($message);
            }
        }
        $this->messageManager->addNoticeMessage(__(
            'The shipment has been created with Polkurier order %1, but its label isn\'t ready yet. '
            . 'Print it later from the order\'s Polkurier tab.',
            $number,
        ));
    }
}
