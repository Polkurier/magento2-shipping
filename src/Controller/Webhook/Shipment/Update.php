<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Webhook\Shipment;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Polkurier\Shipping\Model\Shipment\Status\StatusWebhookHandler;

/**
 * Polkurier's status webhook: the URL to give in the Polkurier panel. Polkurier takes an event as
 * delivered when the answer's body is exactly "OK", whatever the status code.
 *
 * Not a form of this shop's: its signature, not a form key, says where it comes from.
 */
class Update implements HttpPostActionInterface, CsrfAwareActionInterface
{
    /**
     * Its path under the store's base URL: the route's frontName, the controller, and the action,
     * whose underscore Magento reads as this class's namespace.
     */
    public const string PATH = 'polkurier/webhook/shipment_update';

    public function __construct(
        private readonly Http $request,
        private readonly RawFactory $rawFactory,
        private readonly StatusWebhookHandler $statusWebhookHandler,
    ) {}

    public function execute(): Raw
    {
        $outcome = $this->statusWebhookHandler->receive((string)$this->request->getContent());
        $result = $this->rawFactory->create();
        $result->setHeader('Content-Type', 'text/plain', true);
        $result->setHeader('Cache-Control', 'no-store', true);

        return match ($outcome) {
            StatusWebhookHandler::RECEIVED => $result->setContents('OK'),
            StatusWebhookHandler::REFUSED => $result->setHttpResponseCode(403)->setContents('Forbidden'),
            default => $result->setHttpResponseCode(503)->setContents('Not updated'),
        };
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }
}
