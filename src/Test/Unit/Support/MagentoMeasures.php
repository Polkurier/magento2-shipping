<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Magento\Framework\Locale\ResolverInterface;
use Magento\Shipping\Helper\Carrier as CarrierHelper;

/**
 * Magento's own weight and size conversion, as the module uses it, for tests: the carrier
 * helper's real conversions, in an English locale, without the rest of the helper's context.
 */
final class MagentoMeasures
{
    public static function carrierHelper(): CarrierHelper
    {
        $locale = new class implements ResolverInterface {
            public function getDefaultLocalePath()
            {
                return '';
            }

            public function setDefaultLocale($locale)
            {
                return $this;
            }

            public function getDefaultLocale()
            {
                return 'en_US';
            }

            public function setLocale($locale = null)
            {
                return $this;
            }

            public function getLocale()
            {
                return 'en_US';
            }

            public function emulate($scopeId)
            {
                return null;
            }

            public function revert()
            {
                return null;
            }
        };

        return new class ($locale) extends CarrierHelper {
            /**
             * Only the locale is needed by the conversions.
             */
            public function __construct(ResolverInterface $localeResolver)
            {
                $this->localeResolver = $localeResolver;
            }
        };
    }
}
