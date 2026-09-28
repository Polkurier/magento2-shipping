<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Waybill numbers, linked to the carrier's tracking page when Polkurier gave one.
 */
class Waybills extends Column
{
    /**
     * @param array<string, mixed> $dataSource
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
            $url = (string)($item['tracking_url'] ?? '');
            $item[$name] = \implode(', ', \array_map(
                static fn(string $number): string => $url !== ''
                    ? \sprintf('<a href="%s" target="_blank" rel="noopener">%s</a>', \htmlspecialchars($url), \htmlspecialchars($number))
                    : \htmlspecialchars($number),
                $numbers,
            ));
        }

        return $dataSource;
    }
}
