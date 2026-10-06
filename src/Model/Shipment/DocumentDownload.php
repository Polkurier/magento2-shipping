<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;

/**
 * Sends a label or protocol read from Polkurier to the browser as a file.
 */
readonly class DocumentDownload
{
    public function __construct(
        private FileFactory $fileFactory,
    ) {}

    /**
     * @param array{file_name: string, content: string, content_type: string, left_out?: string[]} $document
     *
     * @throws \Exception
     */
    public function send(array $document): ResponseInterface
    {
        return $this->fileFactory->create(
            $document['file_name'],
            ['type' => 'string', 'value' => $document['content'], 'rm' => false],
            DirectoryList::VAR_DIR,
            $document['content_type'],
        );
    }
}
