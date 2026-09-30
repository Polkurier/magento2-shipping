<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Multishipping;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Polkurier\Shipping\Model\Carrier\CarrierConfigs;
use Polkurier\Shipping\Model\Carrier\CarrierLogo;
use Polkurier\Shipping\Model\Carrier\Polkurier;

/**
 * Carrier logos for the multi-address checkout's method lists, which print method titles as plain
 * text: a script puts each logo in front of its method's label.
 */
class MethodLogos extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Polkurier_Shipping::multishipping/method-logos.phtml';

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly CarrierConfigs $carrierConfigs,
        private readonly CarrierLogo $carrierLogo,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<string, string> logo URLs by shipping method code, e.g. "…_DPD"
     */
    public function getLogos(): array
    {
        $store = $this->_storeManager->getStore();
        $logos = [];
        foreach ($this->carrierConfigs->getActive() as $code => $config) {
            $url = $this->carrierLogo->getUrlFor($config, (int)$store->getId());
            if ($url !== null) {
                $logos[Polkurier::CODE . '_' . $code] = $url;
            }
        }

        return $logos;
    }
}
