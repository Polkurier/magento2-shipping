<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Magento\Sales\Api\Data\OrderExtensionInterface;
use Polkurier\Shipping\Api\Data\OrderPointInterface;
use Polkurier\Shipping\Api\Data\ShippingChoiceInterface;

/**
 * The order's extension attributes with the module's pickup point, shipping choice and installation
 * id, as Magento generates them from extension_attributes.xml. Declared here so that tests don't
 * depend on the generated code.
 */
interface OrderExtensionWithPoint extends OrderExtensionInterface
{
    /**
     * Untyped, as Magento generates it.
     *
     * @return OrderPointInterface|null
     */
    public function getPolkurierPoint();

    /**
     * @param OrderPointInterface|null $polkurierPoint
     *
     * @return $this
     */
    public function setPolkurierPoint($polkurierPoint);

    /**
     * @return ShippingChoiceInterface|null
     */
    public function getPolkurierShippingChoice();

    /**
     * @param ShippingChoiceInterface|null $polkurierShippingChoice
     *
     * @return $this
     */
    public function setPolkurierShippingChoice($polkurierShippingChoice);

    /**
     * @return string|null
     */
    public function getPolkurierApplicationId();

    /**
     * @param string|null $polkurierApplicationId
     *
     * @return $this
     */
    public function setPolkurierApplicationId($polkurierApplicationId);
}
