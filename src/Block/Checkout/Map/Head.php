<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Checkout\Map;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Polkurier\Shipping\Model\ConfigProvider;

class Head extends Template
{
    /**
     * @param ConfigProvider $configProvider
     * @param Context $context
     * @param array $data
     */
    public function __construct(
        /**
         * @var ConfigProvider
         */
        private readonly ConfigProvider $configProvider,

        Context $context,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return string
     */
    public function getMapCssUrl(): string
    {
        return $this->configProvider->getMapBaseUrl() . '/assets/dist/points-map.css';
    }

    /**
     * @return string
     */
    public function getMapJsUrl(): string
    {
        return $this->configProvider->getMapBaseUrl() . '/assets/dist/points-map.bundle.js';
    }
}
