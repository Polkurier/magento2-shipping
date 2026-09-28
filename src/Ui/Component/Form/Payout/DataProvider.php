<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Payout;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\UrlInterface;
use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\MoneyBox\AccountScope;
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
        private readonly AccountScope $accountScope,
        private readonly UrlInterface $url,
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
        $websiteId = $this->accountScope->getWebsiteId();
        $storeId = $this->accountScope->getStoreId();
        $data = [
            'website_id' => $websiteId,
            'urls' => [
                'quote' => $this->url->getUrl('polkurier/moneybox/quote'),
                'send_code' => $this->url->getUrl('polkurier/moneybox/sendCode'),
            ],
        ];
        if ($storeId !== null) {
            try {
                $config = $this->payouts->getConfig($storeId);
                $data['payout_type'] = $config->getPayoutType()->value;
                $data['fee_payment_type'] = $config->getFeePayment()->value;
                $data['bank_account_number'] = $config->getIban();
            } catch (LocalizedException) {
                // The form still opens; Polkurier reports the problem again on submit.
            }
            $numbers = [];
            foreach ($this->collection
                ->addFieldToFilter(ShipmentInterface::COD_STATUS, CodStatusEnum::MoneyBox->value)
                ->addFieldToFilter(ShipmentInterface::STORE_ID, ['eq' => $storeId])
                ->getItems() as $shipment) {
                $numbers[] = (string)$shipment->getPolkurierNumber();
            }
            $data['orders'] = \implode("\n", \array_filter($numbers));
        }

        // The form looks the data up by the website parameter, which may be absent.
        return ['' => $data, (string)($websiteId ?? '') => $data];
    }
}
