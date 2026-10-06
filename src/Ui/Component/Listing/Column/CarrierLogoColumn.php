<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Column;

use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Polkurier\Shipping\Model\Carrier\CarrierLogo as Logo;

/**
 * The carrier's checkout logo, as a thumbnail.
 */
class CarrierLogoColumn extends Column
{
    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Logo $logo,
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
        $name = (string)$this->getData('name');
        foreach ($dataSource['data']['items'] ?? [] as $i => $item) {
            $file = (string)($item[$name] ?? '');
            if ($file === '') {
                continue;
            }
            $url = $this->logo->getUrl($file);
            $dataSource['data']['items'][$i][$name . '_src'] = $url;
            $dataSource['data']['items'][$i][$name . '_orig_src'] = $url;
            $dataSource['data']['items'][$i][$name . '_alt'] = (string)($item['carrier_name'] ?? '');
        }

        return $dataSource;
    }
}
