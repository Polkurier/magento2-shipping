<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Helper;

use Magento\OfflinePayments\Model\Cashondelivery;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;

readonly class PaymentHelper
{
    /**
     * @param CartInterface $quote
     *
     * @return bool
     */
    public function isCodAvailable(CartInterface $quote): bool
    {
        return true;
    }

    /**
     * @param OrderInterface $order
     *
     * @return bool
     */
    public function isOrderCod(OrderInterface $order): bool
    {
        return $order->getPayment()?->getMethod() === Cashondelivery::PAYMENT_METHOD_CASHONDELIVERY_CODE;
    }
}
