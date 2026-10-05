<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Model\Config;
use Polkurier\Webservice\Log\LoggedFileWriter;
use Psr\Log\LoggerInterface;

/**
 * Deletes the files the SDK saved for the log once they are older than the configured number of
 * days: the SDK never does. Only files it named ({@see LoggedFileWriter::FILE_NAME_PATTERN}), e.g.
 * "20261005T101500-get_label-1a2b3c4d-file.pdf", so a directory shared with other files keeps them.
 */
readonly class LogFilesCleaner
{
    public function __construct(
        private Config $config,
        private LogFilesDirectory $directory,
        private DateTime $dateTime,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return int how many files were deleted
     */
    public function clean(): int
    {
        $days = $this->config->getLogFilesLifetimeDays();
        $directory = $this->directory->resolve($this->config->getLogFilesDirectory());
        // Also when no longer saved: those saved before go after their time all the same.
        if ($days <= 0 || !\is_dir($directory)) {
            return 0;
        }
        $before = $this->dateTime->gmtTimestamp() - $days * 86400;
        $deleted = 0;
        foreach (new \DirectoryIterator($directory) as $file) {
            if (!$file->isFile() || $file->isLink() || !\preg_match(LoggedFileWriter::FILE_NAME_PATTERN, $file->getFilename())
                || $file->getMTime() >= $before
            ) {
                continue;
            }
            if (@\unlink($file->getPathname())) {
                $deleted++;
            } else {
                $this->logger->warning('Polkurier: a file saved for the log could not be deleted', ['file' => $file->getPathname()]);
            }
        }

        return $deleted;
    }
}
