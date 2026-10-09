<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\Shipment;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Polkurier\Shipping\Ui\Component\Control\OnClickScriptBuilder;

/**
 * Back to the order's page, from the shipment screen.
 */
readonly class BackToOrderButton implements ButtonProviderInterface
{
    public function __construct(
        private RequestInterface $request,
        private UrlInterface $url,
        private OnClickScriptBuilder $onClickScriptBuilder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        return [
            'label' => __('Back to the order'),
            'on_click' => $this->onClickScriptBuilder->location(
                $this->url->getUrl('sales/order/view', ['order_id' => (int)$this->request->getParam('order_id')]),
            ),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
