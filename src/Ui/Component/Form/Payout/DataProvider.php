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
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Controller\Adminhtml\Moneybox\Save;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\MoneyBox\Payouts;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;
use Polkurier\Webservice\Enum\Order\CodStatusEnum;

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
                $data['bank_account_number'] = $config->getIban();
            } catch (LocalizedException) {
                // The form still opens; Polkurier reports the problem again on submit.
            }
            $numbers = [];
            foreach ($this->collection
                ->addFieldToFilter(ShipmentInterface::COD_STATUS, CodStatusEnum::MoneyBox->value)
                ->getItems() as $shipment) {
                $numbers[] = (string)$shipment->getPolkurierNumber();
            }
            $data['orders'] = \implode("\n", \array_filter($numbers));
        }
        // What was chosen, when requesting the payout failed.
        $persisted = $this->dataPersistor->get(Save::PERSISTOR_KEY);
        if (\is_array($persisted)) {
            $data = \array_merge($data, $persisted);
        }
        $this->dataPersistor->clear(Save::PERSISTOR_KEY);

        // The form has no entity: it looks its data up by an absent parameter.
        return ['' => $data];
    }
}
