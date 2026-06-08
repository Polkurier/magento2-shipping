<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\CarrierConfig\Column;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use Polkurier\Shipping\Model\CarrierConfig;

class Thumbnail extends Column
{
    public const string IMAGE_DIR = 'carrier';

    /**
     * @param StoreManagerInterface $storeManager
     * @param UrlInterface $urlBuilder
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        /**
         * @var StoreManagerInterface
         */
        private readonly StoreManagerInterface $storeManager,

        /**
         * @var UrlInterface
         */
        private readonly UrlInterface $urlBuilder,

        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        array $components = [],
        array $data = []
    ) {
        parent::__construct(
            $context,
            $uiComponentFactory,
            $components,
            $data
        );
    }

    /**
     * @param array $dataSource
     *
     * @return array
     * @throws NoSuchEntityException
     */
    public function prepareDataSource(array $dataSource): array
    {
        $mediaBaseUrl = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            if (empty($item[CarrierConfig::FIELD_IMAGE])) {
                continue;
            }
            $url = sprintf(
                '%s/%s/%s',
                $mediaBaseUrl,
                static::IMAGE_DIR,
                ltrim($item[CarrierConfig::FIELD_IMAGE], '/')
            );

            $item['image_src'] = $url;
            $item['image_alt'] = $item['name'] ?? '';
            $item['image_orig_src'] = $url;
            $item['image_upload_url'] = $this->urlBuilder->getUrl(
                'polkurier/carrier_config/imageUpload',
                ['config_id' => $item['config_id']]
            );
        }

        return $dataSource;
    }
}
