<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use Composer\InstalledVersions;
use Magento\Framework\App\Filesystem\DirectoryList;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Sdk\SdkVersionProvider;
use Polkurier\Shipping\Model\Sdk\SerializerConfigProvider;
use Polkurier\Webservice\Serializer\SerializerConfig;
use Polkurier\Webservice\Serializer\SerializerConfigFactory;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapterFactory;

#[CoversClass(SerializerConfigProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class SerializerConfigProviderTest extends TestCase
{
    public function testTheMetadataIsCachedPerSdkVersionAndTimesArePolish(): void
    {
        $directoryList = $this->createStub(DirectoryList::class);
        $directoryList->method('getPath')->willReturn('/shop/var/cache');
        $configs = $this->createStub(SerializerConfigFactory::class);
        $configs->method('create')->willReturnCallback(
            static fn(array $arguments): SerializerConfig => new SerializerConfig(...$arguments),
        );
        $cache = null;
        $caches = $this->createStub(PhpFilesAdapterFactory::class);
        $caches->method('create')->willReturnCallback(function (array $arguments) use (&$cache): PhpFilesAdapter {
            $cache = $arguments;

            return $this->createStub(PhpFilesAdapter::class);
        });

        $provider = new SerializerConfigProvider($directoryList, $configs, $caches);

        self::assertSame($provider->getConfig(), $provider->getConfig(), 'Built once.');
        $version = InstalledVersions::getVersion(SdkVersionProvider::PACKAGE);
        self::assertIsString($version);
        self::assertStringStartsWith('polkurier-' . $version . '_', $cache['namespace'] ?? '');
        self::assertMatchesRegularExpression('/^[-+_.A-Za-z0-9]+$/', $cache['namespace']);
        self::assertSame('/shop/var/cache/polkurier', $cache['directory'] ?? null);
        self::assertSame('Europe/Warsaw', $provider->getConfig()->getTimezone()?->getName());
    }
}
