<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;

/**
 * "Update statuses" on the shipments grid: runs the scheduled update now for the shipments still
 * in motion, however recently they were checked.
 */
class RefreshAll extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipments';

    public function __construct(
        Context $context,
        RequestedShipments $requestedShipments,
        private readonly StatusRefreshService $statusRefreshService,
        private readonly ConfigProvider $configProvider,
        private readonly FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context, $requestedShipments);
    }

    public function execute(): Redirect
    {
        $flash = $this->flashMessageCollectorFactory->create();
        if (!$this->configProvider->hasCredentials()) {
            $flash->error(ConfigProvider::credentialsMissing());
        } else {
            ['refreshed' => $refreshed, 'failed' => $failed] = $this->statusRefreshService->refreshNow();
            if ($failed === 0) {
                $flash->success(__('%1 shipment(s) updated.', $refreshed));
            } else {
                $message = __('%1 shipment(s) updated, %2 not: see the Polkurier log.', $refreshed, $failed);
                $refreshed > 0 ? $flash->warning($message) : $flash->error($message);
            }
        }
        $flash->send($this->messageManager);

        return $this->back();
    }
}
