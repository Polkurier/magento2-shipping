<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Shipment;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Polkurier\Shipping\Api\Data\ShipmentInterface;

/**
 * Waybill numbers, linked to the carrier's tracking page when Polkurier gave one.
 */
class WaybillsColumn extends Column
{
    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Escaper $escaper,
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
        $name = $this->getData('name');
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            $numbers = \array_filter(\explode(',', (string)($item[$name] ?? '')));
            $url = (string)($item[ShipmentInterface::FIELD_TRACKING_URL] ?? '');
            $item[$name] = \implode(', ', \array_map(
                fn(string $number): string => $url !== ''
                    ? \sprintf(
                        '<a href="%s" target="_blank" rel="noopener">%s</a>',
                        $this->escaper->escapeUrl($url),
                        $this->escaper->escapeHtml($number),
                    )
                    : $this->escaper->escapeHtml($number),
                $numbers,
            ));
        }

        return $dataSource;
    }
}
