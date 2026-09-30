<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\ParcelWeightEnum;

#[CoversClass(ParcelWeightEnum::class)]
class ParcelWeightEnumTest extends TestCase
{
    public function testByProductsAParcelWeighsWhatsInIt(): void
    {
        self::assertSame(1.2, ParcelWeightEnum::Products->weigh(25.0, 1.2, true));
    }

    public function testAProductWithoutAWeightLeavesTheTemplatesWeight(): void
    {
        self::assertSame(25.0, ParcelWeightEnum::Products->weigh(25.0, 1.2, false), 'The known part would say too little.');
        self::assertSame(25.0, ParcelWeightEnum::Products->weigh(25.0, 0.0, false));
    }

    public function testByTemplateAParcelWeighsItsTemplatesWeight(): void
    {
        self::assertSame(25.0, ParcelWeightEnum::Template->weigh(25.0, 1.2, true));
    }

    public function testAParcelWithoutATemplateWeighsWhatsInIt(): void
    {
        self::assertSame(1.2, ParcelWeightEnum::Template->weigh(null, 1.2, true));
        self::assertSame(0.0, ParcelWeightEnum::Products->weigh(0.0, 0.0, false), 'Nothing to go by: the weight is left to be typed in.');
    }
}
