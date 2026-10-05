<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Payout;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Polkurier\Shipping\Controller\Adminhtml\Moneybox\Save;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\MasterData;
use Polkurier\Shipping\Model\Mapper\CreateOrderMessageMapper;
use Polkurier\Shipping\Model\MoneyBox\MoneyBoxOrders;
use Polkurier\Shipping\Model\MoneyBox\Payouts;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;

/**
 * The payout request form, prefilled with the money box's own payout settings and the shipments
 * whose cash waits in the money box, as far as Magento last heard.
 */
class DataProvider extends AbstractDataProvider
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
        private readonly Payouts $payouts,
        private readonly Config $config,
        private readonly UrlInterface $url,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly MasterData $masterData,
        private readonly MoneyBoxOrders $moneyBoxOrders,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * The form narrows by its own request parameter, not through the collection.
     */
    public function addFilter(Filter $filter): void
    {
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getData(): array
    {
        $data = [
            'urls' => [
                'quote' => $this->url->getUrl('polkurier/moneybox/quote'),
                'send_code' => $this->url->getUrl('polkurier/moneybox/sendCode'),
            ],
        ];
        if ($this->config->hasCredentials()) {
            try {
                $config = $this->payouts->getConfig();
                $data['payout_type'] = $config->getPayoutType()->value;
                $data['fee_payment_type'] = $config->getFeePayment()->value;
                $data['bank_account_number'] = CreateOrderMessageMapper::accountNumber((string)$config->getIban());
            } catch (LocalizedException) {
                // The form still opens; Polkurier reports the problem again on submit.
            }
            $data['orders'] = \implode("\n", $this->moneyBoxOrders->numbers());
        }
        $data['bank_account_choice'] = $this->accountChoice((string)($data['bank_account_number'] ?? ''));
        // What was chosen, when requesting the payout failed.
        $persisted = $this->dataPersistor->get(Save::PERSISTOR_KEY);
        if (\is_array($persisted)) {
            $data = \array_merge($data, $persisted);
        }
        $this->dataPersistor->clear(Save::PERSISTOR_KEY);

        // The form has no entity: it looks its data up by an absent parameter.
        return ['' => $data];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();
        $meta['payout']['children']['bank_account_choice']['arguments']['data']['config']['options'] = $this->accountOptions();

        return $meta;
    }

    /**
     * The bank accounts a transfer can go to, by number, and another number typed in.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function accountOptions(): array
    {
        $options = [];
        foreach ($this->masterData->getBankAccounts() as $account) {
            $number = CreateOrderMessageMapper::accountNumber($account->getNumber());
            $options[$number] ??= ['value' => $number, 'label' => $account->getName() . ' — ' . $account->getNumber()];
        }
        $options[] = ['value' => Save::ANOTHER_ACCOUNT, 'label' => (string)\__('Another account number')];

        return \array_values($options);
    }

    /**
     * The bank account the money box pays out to, when it's one of them; the default one when the
     * money box names none; otherwise its number typed in.
     */
    private function accountChoice(string $number): string
    {
        $accounts = $this->masterData->getBankAccounts();
        foreach ($accounts as $account) {
            if (self::sameAccount($account->getNumber(), $number)) {
                return CreateOrderMessageMapper::accountNumber($account->getNumber());
            }
        }
        if ($number === '' && $accounts !== []) {
            return CreateOrderMessageMapper::accountNumber($accounts[0]->getNumber());
        }

        return Save::ANOTHER_ACCOUNT;
    }

    /**
     * Whether two numbers are one account, written with or without spaces and the country code.
     */
    private static function sameAccount(string $one, string $other): bool
    {
        $digits = static fn(string $number): string => (string)\preg_replace('/^PL/i', '', CreateOrderMessageMapper::accountNumber($number));

        return $digits($one) !== '' && $digits($one) === $digits($other);
    }
}
