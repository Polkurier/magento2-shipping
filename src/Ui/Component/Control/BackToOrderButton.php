<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * Back to the order's page, from the shipment screen.
 */
readonly class BackToOrderButton implements ButtonProviderInterface
{
    public function __construct(
        private RequestInterface $request,
        private UrlInterface $url,
        private OnClick $onClick,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => \__('Back to the order'),
            'on_click' => $this->onClick->location(
                $this->url->getUrl('sales/order/view', ['order_id' => (int)$this->request->getParam('order_id')]),
            ),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
