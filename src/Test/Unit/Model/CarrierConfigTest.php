<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model;

use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\CarrierConfig\CarrierLabel;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig as CarrierConfigResource;

#[CoversClass(CarrierConfig::class)]
#[CoversClass(CarrierLabel::class)]
class CarrierConfigTest extends TestCase
{
    public function testStoreViewLabelWinsInItsOwnStoreView(): void
    {
        $config = $this->createConfig('Kurier DPD')->setStoreLabels([
            (new CarrierLabel())->setStoreId(2)->setLabel('DPD Courier'),
        ]);

        self::assertSame('DPD Courier', $config->getLabelForStore(2));
        self::assertSame('Kurier DPD', $config->getLabelForStore(1));
    }

    public function testFallsBackToTheCarrierNameWithoutAnyLabel(): void
    {
        self::assertSame('DPD', $this->createConfig(null)->getLabelForStore(1));
    }

    public function testAnEmptyStoreViewLabelDoesNotHideTheDefault(): void
    {
        $config = $this->createConfig('Kurier DPD')->setStoreLabels([
            (new CarrierLabel())->setStoreId(1)->setLabel(''),
        ]);

        self::assertSame('Kurier DPD', $config->getLabelForStore(1));
    }

    private function createConfig(?string $customLabel): CarrierConfig
    {
        $config = new CarrierConfig(
            $this->createStub(Context::class),
            $this->createStub(Registry::class),
            new Json(),
            $this->createStub(CarrierConfigResource::class),
        );

        return $config
            ->setCarrierName('DPD')
            ->setCustomLabel($customLabel);
    }
}
