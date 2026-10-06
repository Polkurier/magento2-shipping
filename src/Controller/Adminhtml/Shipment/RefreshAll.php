<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Shipment;

use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Polkurier\Shipping\Model\Admin\Flash;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Shipment\RequestedShipments;
use Polkurier\Shipping\Model\Shipment\StatusRefresher;

/**
 * "Update statuses" on the shipments grid: what the scheduled update does, now, for the shipments
 * still in motion, however recently they were checked.
 */
class RefreshAll extends AbstractShipmentAction implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::shipments';

    public function __construct(
        Context $context,
        RequestedShipments $requestedShipments,
        private readonly StatusRefresher $statusRefresher,
        private readonly Config $config,
    ) {
        parent::__construct($context, $requestedShipments);
    }

    public function execute(): Redirect
    {
        $flash = new Flash();
        if (!$this->config->hasCredentials()) {
            $flash->error(Config::credentialsMissing());
        } else {
            ['refreshed' => $refreshed, 'failed' => $failed] = $this->statusRefresher->refreshNow();
            if ($failed === 0) {
                $flash->success(\__('%1 shipment(s) updated.', $refreshed));
            } else {
                $message = \__('%1 shipment(s) updated, %2 not: see the Polkurier log.', $refreshed, $failed);
                $refreshed > 0 ? $flash->warning($message) : $flash->error($message);
            }
        }
        $flash->send($this->messageManager);

        return $this->back();
    }
}
