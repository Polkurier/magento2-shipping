<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Model\Sdk\ApplicationIdProvider as Id;

/**
 * Refuses an installation id Polkurier would cut short, or one with characters other than
 * letters, digits, dots, dashes and underscores.
 */
class ApplicationId extends Value
{
    /**
     * @throws LocalizedException
     */
    public function beforeSave(): self
    {
        $entered = \trim((string)$this->getValue());
        $this->setValue($entered);
        if ($entered !== '' && !\preg_match(Id::PATTERN, $entered)) {
            throw new LocalizedException(__(
                'The installation ID can have up to %1 letters, digits, dots, dashes and underscores.',
                Id::MAX_LENGTH,
            ));
        }
        parent::beforeSave();

        return $this;
    }
}
