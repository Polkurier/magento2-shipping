<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\BankAccount;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Deletes the bank account being edited, in Polkurier and Magento. Where the account locks bank
 * accounts with the security PIN, it asks for the PIN; this is the only place such a bank account
 * can be deleted from.
 */
readonly class DeleteButton implements ButtonProviderInterface
{
    /**
     * The form's data source, which says whether bank accounts are locked with a PIN.
     */
    private const string SOURCE = 'polkurier_bank_account_form.polkurier_bank_account_form_data_source';

    public function __construct(
        private RequestInterface $request,
        private UrlInterface $url,
        private Json $json,
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
        $message = $this->json->serialize((string)__('Delete this bank account in Polkurier and in Magento?'));
        $url = $this->json->serialize($this->url->getUrl('polkurier/bankaccount/delete'));

        return [
            'label' => __('Delete'),
            'class' => 'delete',
            // Confirmed, or asked for the security PIN where bank accounts are locked with one.
            'on_click' => \sprintf(
                "require(['Polkurier_Shipping/js/form/bank-account-delete'], function (remove) { "
                . 'remove(%s, %s, %d, %s); }); return false;',
                $message,
                $url,
                $id,
                $this->json->serialize(self::SOURCE),
            ),
            'sort_order' => 20,
        ];
    }
}
