<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Payout;

use Magento\Framework\Api\Filter;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Stdlib\DateTime as DateFormat;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Payout\PayoutManager;
use Polkurier\Shipping\Model\ResourceModel\Shipment\CollectionFactory;

/**
 * Payouts of the money box, read from Polkurier page by page, the newest first. Nothing is stored
 * in Magento.
 */
class DataProvider extends AbstractDataProvider
{
    private int $page = 1;
    private int $pageSize = 20;

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
        private readonly ManagerInterface $messageManager,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    public function setLimit($offset, $size): void
    {
        $this->page = \max(1, (int)$offset);
        $this->pageSize = \max(1, (int)$size);
    }

    public function addFilter(Filter $filter): void
    {
    }

    public function addOrder($field, $direction): void
    {
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, totalRecords: int}
     */
    public function getData(): array
    {
        if (!$this->configProvider->hasCredentials()) {
            return ['items' => [], 'totalRecords' => 0];
        }
        try {
            $list = $this->payoutManager->getPayouts($this->page, $this->pageSize);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return ['items' => [], 'totalRecords' => 0];
        }
        $items = [];
        foreach ($list->getResult() as $payout) {
            $items[] = [
                'number' => $payout->getNumber(),
                'date' => $payout->getDate()?->format(DateFormat::DATETIME_PHP_FORMAT),
                'payout_type' => $payout->getPayoutType(),
                'bank_account_number' => $payout->getIban(),
                'amount' => $payout->getAmount(),
                'fee' => $payout->getFee(),
                'paid_amount' => $payout->getPaidAmount(),
                'status' => $payout->getStatus(),
                'items_count' => $payout->getItemsCount(),
                'cancellable' => (int)$payout->isCancellable(),
            ];
        }

        // Polkurier lists the newest first, by the moment each was requested, but leaves payouts
        // from the same second in no set order. The number breaks the tie.
        \usort($items, static fn(array $a, array $b): int => [$b['date'], $b['number']] <=> [$a['date'], $a['number']]);

        return ['items' => $items, 'totalRecords' => $list->getTotalRows()];
    }
}
