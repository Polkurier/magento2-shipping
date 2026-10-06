<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Column;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Cancelling a payout Polkurier has not sent yet.
 */
class PayoutActionsColumn extends Column
{
    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $url,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = [],
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<string, mixed> $dataSource
     *
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items']) || !$this->authorization->isAllowed('Polkurier_Shipping::money_box_payout')) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            if (empty($item['cancellable'])) {
                continue;
            }
            $item[$this->getData('name')] = ['cancel' => [
                'href' => $this->url->getUrl('polkurier/moneybox/cancel', ['number' => $item['number']]),
                'label' => \__('Cancel'),
                'post' => true,
                'confirm' => ['title' => \__('Cancel payout %1?', $item['number']), 'message' => \__('The money stays in the money box.')],
            ]];
        }

        return $dataSource;
    }
}
