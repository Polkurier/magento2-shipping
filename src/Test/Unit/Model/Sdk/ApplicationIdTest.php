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

    private function applicationId(string $keys): ApplicationId
    {
        $config = $this->createStub(DeploymentConfig::class);
        $config->method('get')->willReturn($keys);

        return new ApplicationId($config);
    }
}
