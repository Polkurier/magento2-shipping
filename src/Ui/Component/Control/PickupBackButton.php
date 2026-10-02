<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Url\DecoderInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

/**
 * "Back" on the collective pickup form: to where the booking was started from, or the order
 * whose Polkurier tab it was opened from, or the shipments grid.
 */
class PickupBackButton implements ButtonProviderInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $url,
        private readonly DecoderInterface $urlDecoder,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        $orderId = (int)$this->request->getParam('order_id');
        $from = (string)$this->request->getParam('uenc');
        $back = $from !== '' ? (string)$this->urlDecoder->decode($from) : '';

        return [
            'label' => $orderId ? \__('Back to the order') : \__('Back'),
            'on_click' => \sprintf(
                "location.href = '%s';",
                match (true) {
                    \str_starts_with($back, (string)$this->url->getBaseUrl()) => $back,
                    $orderId > 0 => $this->url->getUrl('sales/order/view', ['order_id' => $orderId]),
                    default => $this->url->getUrl('polkurier/shipment/index'),
                },
            ),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
