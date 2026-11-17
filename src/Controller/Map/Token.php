<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Map;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Model\PickupPoint\MapTokenProvider;

/**
 * A fresh token for the pickup point map, for a checkout page whose token is gone or about to run
 * out. Every checkout page is given this same token, so it reveals nothing new.
 *
 * The answer is never cached by the page cache or the browser.
 */
class Token implements HttpGetActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly MapTokenProvider $mapTokenProvider,
    ) {}

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store', true);

        return $result->setData($this->mapTokenProvider->getTokenWithExpiry());
    }
}
