<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Composer\InstalledVersions;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\DirectoryList as Directories;
use Polkurier\Webservice\Serializer\SerializerConfigFactory;
use Polkurier\Webservice\Serializer\SerializerProviderFactory as SdkSerializerProviderFactory;
use Polkurier\Webservice\Serializer\SerializerProviderInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapterFactory;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The SDK serializer, with its mapping metadata cached as PHP files under var/cache/polkurier, one
 * folder per installed SDK version, so an SDK update starts with a fresh cache. Neither
 * `bin/magento cache:flush` nor the Cache Management page clears these files; deleting
 * var/cache/polkurier does.
 *
 * Built lazily: reading the mapping metadata is the SDK's biggest start-up cost, and most
 * requests never talk to the API.
 */
class SerializerProvider implements SerializerProviderInterface
{
    public const string CACHE_DIRECTORY = 'polkurier';

    private const string SDK_PACKAGE = 'polkurier/polkurier-sdk';

    private ?SerializerProviderInterface $inner = null;

    public function __construct(
        private readonly Directories $directoryList,
        private readonly SdkSerializerProviderFactory $sdkSerializerProviderFactory,
        private readonly SerializerConfigFactory $serializerConfigFactory,
        private readonly PhpFilesAdapterFactory $phpFilesAdapterFactory,
    ) {}

    public function getSerializer(): SerializerInterface&DenormalizerInterface
    {
        $this->inner ??= $this->sdkSerializerProviderFactory->create([
            'config' => $this->serializerConfigFactory->create([
                'metadataCache' => $this->phpFilesAdapterFactory->create([
                    'namespace' => $this->getCacheNamespace(),
                    'defaultLifetime' => 0,
                    'directory' => $this->directoryList->getPath(DirectoryList::CACHE) . '/' . self::CACHE_DIRECTORY,
                ]),
                'timezone' => PolkurierTimeZone::getZone(),
            ]),
        ]);

        return $this->inner->getSerializer();
    }

    /**
     * The metadata of an earlier SDK may no longer fit its classes, so each version has its own.
     */
    private function getCacheNamespace(): string
    {
        try {
            $version = InstalledVersions::getVersion(self::SDK_PACKAGE)
                . '-' . InstalledVersions::getReference(self::SDK_PACKAGE);
        } catch (\OutOfBoundsException) {
            $version = '';
        }

        // The characters a cache namespace may have.
        return self::CACHE_DIRECTORY . '-' . \preg_replace('/[^-+_.A-Za-z0-9]/', '_', $version);
    }
}
