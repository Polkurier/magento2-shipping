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
use Magento\Ui\DataProvider\AbstractDataProvider;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\MoneyBox\Payouts;
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
        private readonly Payouts $payouts,
        private readonly Config $config,
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
        if (!$this->config->hasCredentials()) {
            return ['items' => [], 'totalRecords' => 0];
        }
        try {
            $list = $this->payouts->getPayouts($this->page, $this->pageSize);
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return ['items' => [], 'totalRecords' => 0];
        }
        $items = [];
        foreach ($list->getResult() as $payout) {
            $items[] = [
                'number' => $payout->getNumber(),
                'date' => $payout->getDate()?->format('Y-m-d H:i:s'),
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

        // Polkurier lists the newest first, by the moment each was requested, but leaves those of
        // the same second in no set order: the number settles it.
        \usort($items, static fn(array $a, array $b): int => [$b['date'], $b['number']] <=> [$a['date'], $a['number']]);

        return ['items' => $items, 'totalRecords' => $list->getTotalRows()];
    }
}
