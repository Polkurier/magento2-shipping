<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Deletes the bank account being edited, in Polkurier and Magento, sending the security PIN
 * typed into the form: the only place a PIN-locked account can be deleted from.
 */
class DeleteBankAccountButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $url,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $id = (int)$this->request->getParam('id');
        if ($id === 0) {
            return [];
        }
        $message = (string)\json_encode((string)\__('Delete this bank account in Polkurier and in Magento?'));
        $url = (string)\json_encode($this->url->getUrl('polkurier/bankaccount/save'), \JSON_UNESCAPED_SLASHES);

        return [
            'label' => \__('Delete'),
            'class' => 'delete',
            // The PIN is read when the button is clicked, not when the page is built.
            'on_click' => \sprintf(
                "deleteConfirm(%s, %s, {data: {id: %d, delete: 1, confirmation_pin: (document.querySelector('[name=\"confirmation_pin\"]') || {}).value || ''}}); return false;",
                $message,
                $url,
                $id,
            ),
            'sort_order' => 20,
        ];
    }
}
