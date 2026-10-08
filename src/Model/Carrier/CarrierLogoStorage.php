<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Carrier;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Logger\Logger;

/**
 * Carrier logos: uploaded by the merchant, kept in the media directory and shown next to the
 * methods in checkout.
 */
readonly class CarrierLogoStorage
{
    public const string DIRECTORY = 'shipping/carrier_logos';

    /**
     * Raster formats only: an SVG can carry scripts.
     */
    public const array EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    public function __construct(
        private UploaderFactory $uploaderFactory,
        private Filesystem $filesystem,
        private StoreManagerInterface $storeManager,
        private Logger $logger,
    ) {}

    /**
     * @return array{file: string, name: string, url: string, size: int, type: string}
     * @throws LocalizedException
     */
    public function upload(string $fileId): array
    {
        try {
            $uploader = $this->uploaderFactory->create(['fileId' => $fileId]);
            $uploader->setAllowedExtensions(self::EXTENSIONS);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);
            $uploader->setAllowCreateFolders(true);
            $result = $uploader->save(
                $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)->getAbsolutePath(self::DIRECTORY),
            );
        } catch (LocalizedException $e) {
            throw $e;
        } catch (\Exception $e) {
            // The uploader's own messages may name the server's paths.
            $this->logger->error('Polkurier carrier logo not uploaded', ['exception' => $e]);
            throw new LocalizedException(__('The logo could not be uploaded.'), $e);
        }
        if (!\is_array($result) || empty($result['file'])) {
            throw new LocalizedException(__('The logo could not be uploaded.'));
        }
        $file = self::DIRECTORY . '/' . \ltrim((string)$result['file'], '/');

        return [
            'file' => $file,
            'name' => (string)$result['file'],
            'url' => $this->getUrl($file),
            'size' => (int)($result['size'] ?? 0),
            'type' => (string)($result['type'] ?? ''),
        ];
    }

    public function getUrl(string $file, ?int $storeId = null): string
    {
        $store = $this->storeManager->getStore($storeId);
        $base = $store instanceof Store ? $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) : '';

        return \rtrim((string)$base, '/') . '/' . \ltrim($file, '/');
    }

    public function getUrlFor(CarrierConfigInterface $config, ?int $storeId = null): ?string
    {
        $logo = $config->getLogo();

        return $logo !== null ? $this->getUrl($logo, $storeId) : null;
    }

    /**
     * Whether a path from the carrier form points into the logo directory, and nowhere else.
     */
    public function isOwnFile(string $file): bool
    {
        return \str_starts_with($file, self::DIRECTORY . '/') && !\str_contains($file, '..')
            && \in_array(\strtolower(\pathinfo($file, \PATHINFO_EXTENSION)), self::EXTENSIONS, true);
    }
}
