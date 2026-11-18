<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Payout;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Polkurier\Shipping\Controller\Adminhtml\Payout\Cancel as PayoutCancel;

/**
 * Cancelling a payout Polkurier has not sent yet.
 */
class ActionsColumn extends Column
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
        if (!isset($dataSource['data']['items'])
            || !$this->authorization->isAllowed(PayoutCancel::ADMIN_RESOURCE)
        ) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            if (empty($item['cancellable'])) {
                continue;
            }
            $item[$this->getData('name')] = ['cancel' => [
                'href' => $this->url->getUrl(PayoutCancel::ROUTE, ['number' => $item['number']]),
                'label' => __('Cancel'),
                'post' => true,
                'confirm' => [
                    'title' => __('Cancel payout %1?', $item['number']),
                    'message' => __('The money stays in the money box.'),
                ],
            ]];
        }

        return $dataSource;
    }
}
