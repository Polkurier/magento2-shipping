<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Enum\Config\ParcelWeightEnum;

#[CoversClass(ParcelWeightEnum::class)]
class ParcelWeightEnumTest extends TestCase
{
    public function testByProductsAParcelWeighsWhatsInIt(): void
    {
        self::assertSame(1.2, ParcelWeightEnum::Products->weigh(25.0, 1.2, true));
    }

    public function testAProductWithoutAWeightLeavesTheTemplatesWeight(): void
    {
        self::assertSame(
            25.0,
            ParcelWeightEnum::Products->weigh(25.0, 1.2, false),
            'The known part would say too little.',
        );
        self::assertSame(25.0, ParcelWeightEnum::Products->weigh(25.0, 0.0, false));
    }

    public function testByTemplateAParcelWeighsItsTemplatesWeight(): void
    {
        self::assertSame(25.0, ParcelWeightEnum::Template->weigh(25.0, 1.2, true));
    }

    public function testAParcelWithoutATemplateWeighsWhatsInIt(): void
    {
        self::assertSame(1.2, ParcelWeightEnum::Template->weigh(null, 1.2, true));
        self::assertSame(
            0.0,
            ParcelWeightEnum::Products->weigh(0.0, 0.0, false),
            'Nothing to go by: the weight is left to be typed in.',
        );
    }

    public function testTheTareIsAddedOnlyToWhatsInIt(): void
    {
        self::assertSame(2.75, ParcelWeightEnum::Products->weigh(10.0, 2.5, true, 0.25));
        self::assertSame(
            10.0,
            ParcelWeightEnum::Template->weigh(10.0, 2.5, true, 0.25),
            'The template\'s weight holds the packaging.',
        );
        self::assertSame(
            10.0,
            ParcelWeightEnum::Products->weigh(10.0, 2.5, false, 0.25),
            'Unweighed: the template\'s weight.',
        );
        self::assertSame(
            2.5,
            ParcelWeightEnum::Products->weigh(null, 2.5, true, 0.25),
            'No template, no packaging known.',
        );
    }
}
