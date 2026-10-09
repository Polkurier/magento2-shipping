<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use Composer\InstalledVersions;
use Magento\Framework\Filesystem\DirectoryList as Directories;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Sdk\SerializerProvider;
use Polkurier\Webservice\Serializer\SerializerConfig;
use Polkurier\Webservice\Serializer\SerializerConfigFactory;
use Polkurier\Webservice\Serializer\SerializerProviderFactory;
use Polkurier\Webservice\Serializer\SerializerProviderInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapterFactory;
use Symfony\Component\Serializer\Serializer;

#[CoversClass(SerializerProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class SerializerProviderTest extends TestCase
{
    public function testTheMetadataIsCachedPerSdkVersion(): void
    {
        $directories = $this->createStub(Directories::class);
        $directories->method('getPath')->willReturn('/shop/var/cache');
        $configs = $this->createStub(SerializerConfigFactory::class);
        $configs->method('create')->willReturn($this->createStub(SerializerConfig::class));
        $inner = $this->createStub(SerializerProviderInterface::class);
        $inner->method('getSerializer')->willReturn(new Serializer());
        $providers = $this->createStub(SerializerProviderFactory::class);
        $providers->method('create')->willReturn($inner);
        $cache = null;
        $caches = $this->createStub(PhpFilesAdapterFactory::class);
        $caches->method('create')->willReturnCallback(function (array $arguments) use (&$cache): PhpFilesAdapter {
            $cache = $arguments;

            return $this->createStub(PhpFilesAdapter::class);
        });

        (new SerializerProvider($directories, $providers, $configs, $caches))->getSerializer();

        $version = InstalledVersions::getVersion('polkurier/polkurier-sdk');
        self::assertIsString($version);
        self::assertStringStartsWith('polkurier-' . $version . '-', $cache['namespace'] ?? '');
        self::assertMatchesRegularExpression('/^[-+_.A-Za-z0-9]+$/', $cache['namespace']);
        self::assertSame('/shop/var/cache/polkurier', $cache['directory'] ?? null);
    }
}
