<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Magento\Sales\Api\Data\OrderExtensionInterface;
use Polkurier\Shipping\Api\Data\OrderPointInterface;

/**
 * The order's extension attributes with the module's pickup point, as Magento generates them from
 * extension_attributes.xml. Declared here so that tests don't depend on the generated code.
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
     * @return $this
     */
    public function setPolkurierPoint($polkurierPoint);
}
