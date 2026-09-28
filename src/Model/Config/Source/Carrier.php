<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\App\RequestInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\WebsiteScope;
use Polkurier\Webservice\Exception\Exception as PolkurierException;

/**
 * The carriers of the Polkurier account configured for the scope being edited.
 * Empty until the account is configured and reachable.
 */
class Carrier extends AbstractOptions
{
    public function __construct(
        protected readonly CarrierCatalog $catalog,
        private readonly WebsiteScope $websiteScope,
        private readonly RequestInterface $request,
    ) {}

    public function toArray(): array
    {
        $website = (int)$this->request->getParam('website');
        $store = $this->request->getParam('store');
        $storeId = $store !== null ? (int)$store : ($website ? $this->websiteScope->getStoreId($website) : 0);

        try {
            $carriers = $this->loadCarriers($storeId);
        } catch (PolkurierException) {
            return ['' => (string)\__('-- Configure the Polkurier account first --')];
        }
        $options = ['' => (string)\__('-- None --')];
        foreach ($carriers as $code => $carrier) {
            $options[$code] = \sprintf('%s (%s)', $carrier->getName(), $code);
        }

        return $options;
    }

    /**
     * @return array<string, \Polkurier\Webservice\Response\Carrier>
     * @throws PolkurierException
     */
    protected function loadCarriers(int $storeId): array
    {
        return $this->catalog->getCarriers($storeId);
    }
}
