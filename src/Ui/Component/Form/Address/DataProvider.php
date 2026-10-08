<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Address;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\UrlInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sync\EntityHandlerInterface;
use Polkurier\Shipping\Ui\Component\Form\SyncableEntityDataProvider;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * Data of the address form, and what its sender drop-off point needs to be chosen on the map: the
 * map's address, where its token comes from, and the point providers of the carriers that take
 * parcels at points.
 */
class DataProvider extends SyncableEntityDataProvider
{
    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        AbstractCollection $collection,
        RequestInterface $request,
        DataPersistorInterface $dataPersistor,
        EntityHandlerInterface $entityHandler,
        private readonly ConfigProvider $configProvider,
        private readonly UrlInterface $url,
        private readonly CarrierCatalog $carrierCatalog,
        private readonly CarrierFeatureResolver $carrierFeatureResolver,
        private readonly Logger $logger,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $collection,
            $request,
            $dataPersistor,
            $entityHandler,
            $meta,
            $data,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function getMeta(): array
    {
        $meta = parent::getMeta();
        $meta['general']['children']['point_id']['arguments']['data']['config'] = [
            'map' => [
                'baseUrl' => $this->configProvider->getMapUrl() . '/',
                'tokenUrl' => $this->url->getUrl('polkurier/shipment/mapToken'),
            ],
            'providers' => $this->senderPointProviders(),
        ];

        return $meta;
    }

    /**
     * The point providers of every carrier that takes parcels at points; none when the carrier list
     * can't be read, which leaves the map showing all of them.
     *
     * @return string[]
     */
    private function senderPointProviders(): array
    {
        try {
            $carriers = $this->carrierCatalog->getCarriers();
        } catch (PolkurierException $e) {
            $this->logger->warning('Carrier list unavailable for the drop-off point map', ['exception' => $e]);

            return [];
        }
        $providers = [];
        foreach ($carriers as $carrier) {
            if ($this->carrierFeatureResolver->getSenderPointRequirement($carrier) !== PointRequirementEnum::None) {
                \array_push($providers, ...$this->carrierFeatureResolver->getPointProviders($carrier));
            }
        }

        return \array_values(\array_unique($providers));
    }
}
