<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\App\Filesystem\DirectoryList;

/**
 * Where the SDK saves files of logged requests and answers: the directory entered (relative to
 * Magento's root unless absolute), or var/log/polkurier/files. Inside it, each month has a
 * directory of its own, e.g. 2026/10. Never a directory the web server serves: labels show the
 * recipient's name and address.
 */
readonly class LogFilesDirectoryResolver
{
    public const string DEFAULT = 'polkurier/files';

    public function __construct(
        private DirectoryList $directoryList,
    ) {}

    /**
     * @param string $entered as configured; empty for the default
     */
    public function resolve(string $entered): string
    {
        $entered = \trim($entered);
        if ($entered === '') {
            return $this->directoryList->getPath(DirectoryList::LOG) . '/' . self::DEFAULT;
        }

        return self::normalize(
            \str_starts_with($entered, '/') ? $entered : $this->directoryList->getRoot() . '/' . $entered,
        );
    }

    /**
     * The month's directory inside the resolved one, e.g. ".../files/2026/10".
     */
    public function ofMonth(string $directory, int $timestamp): string
    {
        return $directory . '/' . \gmdate('Y/m', $timestamp);
    }

    /**
     * Whether the web server would serve files saved there.
     */
    public function isPublic(string $directory): bool
    {
        $directory = self::normalize($directory);
        $pub = $this->directoryList->getPath(DirectoryList::PUB);
        foreach (\array_unique(\array_filter([self::normalize($pub), \realpath($pub) ?: null])) as $public) {
            if ($directory === $public || \str_starts_with($directory, $public . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolves "." and ".." by hand: realpath() can't, for a directory not created yet.
     */
    private static function normalize(string $path): string
    {
        $parts = [];
        foreach (\explode('/', $path) as $part) {
            if ($part === '' || $part === '.') {
                continue;
            }
            if ($part === '..') {
                \array_pop($parts);
            } else {
                $parts[] = $part;
            }
        }

        return '/' . \implode('/', $parts);
    }
}
