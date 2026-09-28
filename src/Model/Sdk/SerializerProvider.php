<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\DirectoryList as Directories;
use Polkurier\Webservice\Serializer\SerializerConfig;
use Polkurier\Webservice\Serializer\SerializerProvider as SdkSerializerProvider;
use Polkurier\Webservice\Serializer\SerializerProviderInterface;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * The SDK serializer, with its mapping metadata cached under var/cache so that
 * `bin/magento cache:flush` clears it along with the rest.
 *
 * Built lazily: reading the mapping metadata is the SDK's biggest start-up cost, and most
 * requests never talk to the API.
 */
class SerializerProvider implements SerializerProviderInterface
{
    public const string CACHE_DIRECTORY = 'polkurier';

    private ?SerializerProviderInterface $inner = null;

    public function __construct(
        private readonly Directories $directoryList,
    ) {}

    public function getSerializer(): SerializerInterface&DenormalizerInterface
    {
        $this->inner ??= new SdkSerializerProvider(new SerializerConfig(
            metadataCache: new PhpFilesAdapter(
                self::CACHE_DIRECTORY,
                0,
                $this->directoryList->getPath(DirectoryList::CACHE) . '/' . self::CACHE_DIRECTORY,
            ),
            timezone: new \DateTimeZone('Europe/Warsaw'),
        ));

        return $this->inner->getSerializer();
    }
}
