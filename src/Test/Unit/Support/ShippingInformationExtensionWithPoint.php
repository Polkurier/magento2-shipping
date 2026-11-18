<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Magento\Checkout\Api\Data\ShippingInformationExtensionInterface;

/**
 * The shipping information's extension attributes with the module's pickup point, as Magento
 * generates them from extension_attributes.xml. Declared here so that tests don't depend on the
 * generated code.
 */
interface ShippingInformationExtensionWithPoint extends ShippingInformationExtensionInterface
{
    /**
     * @return string|null
     */
    public function getPickupPointId();
}
