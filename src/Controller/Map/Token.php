<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Map;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Model\Checkout\MapTokenProvider;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Psr\Log\LoggerInterface;

/**
 * A fresh token for the pickup point map, for a checkout page whose token is gone or about to run
 * out: the same token every checkout page is given, so it tells no one anything new.
 *
 * The answer names nothing of Polkurier's; it's never cached by the page cache or the browser.
 */
class Token implements HttpGetActionInterface
{
    /**
     * The route, by its id: Magento's URLs turn it into the frontName pickup-points.
     */
    public const string ROUTE = 'polkurier_points/map/token';

    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly MapTokenProvider $tokenProvider,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $result->setHeader('Cache-Control', 'no-store', true);
        try {
            $token = $this->tokenProvider->getToken();
            $expires = $this->tokenProvider->getExpiry();
        } catch (PolkurierException $e) {
            $this->logger->error('Pickup point map token unavailable', ['error' => $e->getMessage()]);
            $token = '';
            $expires = 0;
        }

        return $result->setData(['token' => $token, 'expires' => $expires]);
    }
}
