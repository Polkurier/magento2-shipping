<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use Magento\Framework\App\DeploymentConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sdk\ApplicationIdProvider;

#[CoversClass(ApplicationIdProvider::class)]
class ApplicationIdProviderTest extends TestCase
{
    public function testTheIdIsMadeFromTheFirstKeyAndFitsPolkuriersColumn(): void
    {
        $id = $this->createProvider('first-key')->get();

        self::assertMatchesRegularExpression('/^magento2-[0-9a-f]{12}$/', $id);
        self::assertLessThanOrEqual(50, \strlen($id));
        self::assertSame(
            $id,
            $this->createProvider("first-key\nrotated-key")->get(),
            'A rotated key keeps the id.',
        );
        self::assertNotSame($id, $this->createProvider('another-installation')->get());
    }

    public function testAnEnteredIdIsSentInsteadButTheMadeOneStaysKnown(): void
    {
        $made = $this->createProvider('first-key')->get();
        $applicationId = $this->createProvider('first-key', 'shop-staging');

        self::assertSame('shop-staging', $applicationId->get());
        self::assertSame($made, $applicationId->computeId(), 'Said beside the setting, as what an empty one means.');
    }

    public function testEnteredIdsAreLettersDigitsDotsDashesAndUnderscoresThatFitPolkuriersColumn(): void
    {
        foreach (['shop-staging', 'magento2.Staging_01', \str_repeat('a', 50)] as $id) {
            self::assertMatchesRegularExpression(ApplicationIdProvider::PATTERN, $id);
        }
        foreach (['', 'shop staging', 'sklep-ąę', 'shop/staging', \str_repeat('a', 51)] as $id) {
            self::assertDoesNotMatchRegularExpression(ApplicationIdProvider::PATTERN, $id);
        }
    }

    private function createProvider(string $keys, string $entered = ''): ApplicationIdProvider
    {
        $deploymentConfig = $this->createStub(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturn($keys);
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getApplicationIdOverride')->willReturn($entered);

        return new ApplicationIdProvider($deploymentConfig, $config);
    }
}
