<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Composer\InstalledVersions;

/**
 * The installed Polkurier SDK, for keeping cached SDK objects and metadata apart per version:
 * what an earlier SDK cached may no longer fit its classes.
 */
final class SdkVersionProvider
{
    public const string PACKAGE = 'polkurier/polkurier-sdk';

    /**
     * Its version and commit ("2.0.0@0123abc…"), or "" when Composer doesn't know the package.
     */
    public static function getInstalledVersion(): string
    {
        try {
            return InstalledVersions::getVersion(self::PACKAGE) . '@' . InstalledVersions::getReference(self::PACKAGE);
        } catch (\OutOfBoundsException) {
            return '';
        }
    }
}
