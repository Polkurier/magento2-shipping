<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Shipping\Enum\AdditionalServiceExtensionEnum;
use Polkurier\Webservice\Enum\AdditionalServiceEnum;

class DefaultAdditionalService implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => AdditionalServiceExtensionEnum::INSURANCE->value, 'label' => \__('Insurance')],
            ['value' => AdditionalServiceEnum::SMS_NOTIFICATION_RECIPIENT->value, 'label' => __('SMS notifications')],
            ['value' => AdditionalServiceEnum::SMS_NOTIFICATION_RECIPIENT_WITH_NAME->value, 'label' => __('SMS notifications with a custom name')],
            ['value' => AdditionalServiceExtensionEnum::COVER_ADDRESS->value, 'label' => \__('Cover address')],
        ];
    }
}
