<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Payout;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Enum\Adminhtml\FormMemoryEnum;
use Polkurier\Shipping\Model\BankAccount\BankAccountMapper;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Payout\MoneyBoxOrderFinder;
use Polkurier\Shipping\Model\Payout\PayoutManager;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Shipping\Ui\Component\Form\FormDataProvider;

/**
 * The payout request form, prefilled with the money box's own payout settings and the shipments
 * whose cash waits in the money box, as far as Magento last heard.
 */
class DataProvider extends FormDataProvider
{
    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly PayoutManager $payoutManager,
        private readonly ConfigProvider $configProvider,
        private readonly UrlInterface $url,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly BankAccountRepositoryInterface $bankAccountRepository,
        private readonly MoneyBoxOrderFinder $moneyBoxOrderFinder,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();
        $meta['payout']['children']['bank_account_choice']['arguments']['data']['config']['options']
            = $this->getAccountOptions();
        // The number field shows only when another account is chosen.
        $meta['payout']['children']['bank_account_number']['arguments']['data']['config']['shownWhen']['bank_account_choice']
            = PayoutManager::ANOTHER_ACCOUNT;

        return $meta;
    }

    protected function load(): array
    {
        $data = [
            'urls' => [
                'quote' => $this->url->getUrl('polkurier/payout/quote'),
                'send_code' => $this->url->getUrl('polkurier/payout/sendCode'),
            ],
        ];
        if ($this->configProvider->hasCredentials()) {
            try {
                $config = $this->payoutManager->getConfig();
                $data['payout_type'] = $config->getPayoutType()->value;
                $data['fee_payment_type'] = $config->getFeePayment()->value;
                $data['bank_account_number'] = BankAccountMapper::normalizeNumber((string)$config->getIban());
            } catch (LocalizedException) {
                // The form still opens; Polkurier reports the problem again on submit.
            }
            $data['orders'] = \implode("\n", $this->moneyBoxOrderFinder->findNumbers());
        }
        $data['bank_account_choice'] = $this->getAccountChoice((string)($data['bank_account_number'] ?? ''));
        // What was chosen, when requesting the payout failed.
        $persisted = $this->dataPersistor->get(FormMemoryEnum::Payout->value);
        if (\is_array($persisted)) {
            $data = \array_merge($data, $persisted);
        }
        $this->dataPersistor->clear(FormMemoryEnum::Payout->value);

        // The form has no entity, so its data is keyed by the value of an absent parameter.
        return ['' => $data];
    }

    /**
     * The bank accounts a transfer can go to, by number, and another number typed in.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function getAccountOptions(): array
    {
        $options = [];
        foreach ($this->bankAccountRepository->getAvailable() as $account) {
            $number = BankAccountMapper::normalizeNumber($account->getNumber());
            $options[$number] ??= ['value' => $number, 'label' => $account->getName() . ' — ' . $account->getNumber()];
        }
        $options[] = ['value' => PayoutManager::ANOTHER_ACCOUNT, 'label' => (string)__('Another account number')];

        return \array_values($options);
    }

    /**
     * The account to preselect: the bank account the money box pays out to, when it's one of them;
     * the default one when the money box names none; otherwise another account, with the money
     * box's number typed in.
     */
    private function getAccountChoice(string $number): string
    {
        $accounts = $this->bankAccountRepository->getAvailable();
        foreach ($accounts as $account) {
            if (self::isSameAccount($account->getNumber(), $number)) {
                return BankAccountMapper::normalizeNumber($account->getNumber());
            }
        }
        if ($number === '' && $accounts !== []) {
            return BankAccountMapper::normalizeNumber($accounts[0]->getNumber());
        }

        return PayoutManager::ANOTHER_ACCOUNT;
    }

    /**
     * Whether two numbers are the same account, written with or without spaces and the country code.
     */
    private static function isSameAccount(string $one, string $other): bool
    {
        $digits = static fn(string $number): string => (string)\preg_replace(
            '/^PL/i',
            '',
            BankAccountMapper::normalizeNumber($number),
        );

        return $digits($one) !== '' && $digits($one) === $digits($other);
    }
}
