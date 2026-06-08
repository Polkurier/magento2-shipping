<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing;

use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\DataObject;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider as BaseDataProvider;

class DataProvider extends BaseDataProvider
{
    /**
     * @param SearchResultInterface $searchResult
     *
     * @return array
     */
    protected function searchResultToOutput(SearchResultInterface $searchResult): array
    {
        return [
            'items' => \array_values(\array_map(
                [$this, 'getModelData'],
                $searchResult->getItems()
            )),
            'totalRecords' => $searchResult->getTotalCount(),
        ];
    }

    /**
     * @param DataObject $model
     *
     * @return array
     */
    protected function getModelData(DataObject $model): array
    {
        return $model->getData();
    }
}
