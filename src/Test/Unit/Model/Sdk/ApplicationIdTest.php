<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use Magento\Framework\App\DeploymentConfig;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Sdk\ApplicationId;

#[CoversClass(ApplicationId::class)]
class ApplicationIdTest extends TestCase
{
    public function testTheIdIsMadeFromTheFirstKeyAndFitsPolkuriersColumn(): void
    {
        $id = $this->applicationId('first-key')->get();

        self::assertMatchesRegularExpression('/^magento2-[0-9a-f]{12}$/', $id);
        self::assertLessThanOrEqual(50, \strlen($id));
        self::assertSame($id, $this->applicationId("first-key\nrotated-key")->get(), 'A rotated key keeps the id.');
        self::assertNotSame($id, $this->applicationId('another-installation')->get());
    }

    public function testAnEnteredIdIsSentInsteadButTheMadeOneStaysKnown(): void
    {
        $made = $this->applicationId('first-key')->get();
        $applicationId = $this->applicationId('first-key', 'shop-staging');

        self::assertSame('shop-staging', $applicationId->get());
        self::assertSame($made, $applicationId->computed(), 'Said beside the setting, as what an empty one means.');
    }

    public function testEnteredIdsAreLettersDigitsDotsDashesAndUnderscoresThatFitPolkuriersColumn(): void
    {
        foreach (['shop-staging', 'magento2.Staging_01', \str_repeat('a', 50)] as $id) {
            self::assertMatchesRegularExpression(ApplicationId::PATTERN, $id);
        }
        foreach (['', 'shop staging', 'sklep-ąę', 'shop/staging', \str_repeat('a', 51)] as $id) {
            self::assertDoesNotMatchRegularExpression(ApplicationId::PATTERN, $id);
        }
    }

    private function applicationId(string $keys, string $entered = ''): ApplicationId
    {
        $deploymentConfig = $this->createStub(DeploymentConfig::class);
        $deploymentConfig->method('get')->willReturn($keys);
        $config = $this->createStub(Config::class);
        $config->method('getApplicationIdOverride')->willReturn($entered);

        return new ApplicationId($deploymentConfig, $config);
    }
}
