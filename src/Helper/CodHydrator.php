<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Helper;

use Polkurier\Shipping\Api\Data\Order\CodInterfaceFactory;
use Polkurier\Shipping\Api\Data\OrderInterface;
use Polkurier\Shipping\Model\ResourceModel\Order;

readonly class CodHydrator
{
    /**
     * @param CodInterfaceFactory $codFactory
     */
    public function __construct(
        /**
         * @var CodInterfaceFactory
         */
        private CodInterfaceFactory $codFactory,
    ) {}

    /**
     * @param OrderInterface $order
     *
     * @return void
     */
    public function hydrateCod(OrderInterface $order): void
    {
        $codData = \array_filter(
            $order->getData(),
            fn (string $key): bool => \str_starts_with($key, Order::COD_FIELDS_PREFIX),
            ARRAY_FILTER_USE_KEY
        );
        if (empty($codData)) {
            return;
        }

        foreach ($codData as $key => $value) {
            $codData[\substr($key, \strlen(Order::COD_FIELDS_PREFIX))] = $value;
            unset($codData[$key]);
        }

        $cod = $this->codFactory->create();
        $cod->setData($codData);
        $order->setCod($cod);
    }
}
