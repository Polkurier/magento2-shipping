<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

use Magento\Backend\Model\Session;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Ui\Component\Control\OnClickScriptBuilder;

/**
 * "Back" on the shipment details page: to the admin page it was opened from (the shipments grid,
 * the order's Polkurier tab, Magento's shipments grid or shipment page), or the shipments grid.
 * The page it was opened from is kept in the admin session, so it isn't lost when an action run on
 * this page comes back to it.
 */
class BackButton extends AbstractButton
{
    private const string SESSION_KEY = 'polkurier_shipment_view_back';

    public function __construct(
        RequestInterface $request,
        UrlInterface $url,
        AuthorizationInterface $authorization,
        ShipmentRepositoryInterface $shipmentRepository,
        OnClickScriptBuilder $onClickScriptBuilder,
        private readonly Session $session,
    ) {
        parent::__construct($request, $url, $authorization, $shipmentRepository, $onClickScriptBuilder);
    }

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $id = (int)$this->request->getParam('id');
        $referer = $this->request instanceof Http ? (string)$this->request->getServer('HTTP_REFERER') : '';
        // Only a page of this admin, and not this page again after one of its actions.
        if (\str_starts_with($referer, (string)$this->url->getBaseUrl())
            && !\str_contains($referer, '/polkurier/shipment/view/')
        ) {
            $this->session->setData(self::SESSION_KEY, ['id' => $id, 'url' => $referer]);
        }
        $back = $this->session->getData(self::SESSION_KEY);

        return [
            'label' => __('Back'),
            'on_click' => $this->onClickScriptBuilder->location(
                \is_array($back) && ($back['id'] ?? null) === $id
                    ? (string)$back['url']
                    : $this->url->getUrl('polkurier/shipment/index'),
            ),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
