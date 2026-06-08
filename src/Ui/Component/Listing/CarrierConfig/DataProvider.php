<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\CarrierConfig;

use Magento\Framework\DataObject;
use Polkurier\Shipping\Enum\PricingModelEnum;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\Shipment\PriceCalculator\Fixed;
use Polkurier\Shipping\Ui\Component\Listing\DataProvider as BaseDataProvider;
use Polkurier\Webservice\Model\Entity\Carrier;

class DataProvider extends BaseDataProvider
{
    /**
     * @param CarrierConfig $model
     *
     * @return array
     */
    protected function getModelData(DataObject $model): array
    {
        $data = $model->getData();
        $data['carrier_name'] = $model->getExtData()[Carrier::FIELD_NAME];
        if ($model->getPricingModel() === PricingModelEnum::FIXED) {
            $data['price'] = $model->getPriceInfo()[Fixed::PRICE_INFO_PRICE] ?? 0.0;
        }

        return $data;
    }
}
