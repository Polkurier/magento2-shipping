<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Webservice\Enum\AdditionalServiceEnum;

readonly class AdditionalService implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => AdditionalServiceEnum::REFUND_DOCS->value, 'label' => __('Refund documents')],
            ['value' => AdditionalServiceEnum::COURIER_WITH_LABEL->value, 'label' => __('Courier with printed out documents')],
            ['value' => AdditionalServiceEnum::WEEK_COLLECTION->value, 'label' => __('Weekend delivery')],
            ['value' => AdditionalServiceEnum::SMS_NOTIFICATION_RECIPIENT->value, 'label' => __('SMS notifications')],
            ['value' => AdditionalServiceEnum::SMS_NOTIFICATION_RECIPIENT_WITH_NAME->value, 'label' => __('SMS notifications with a custom name')],
            ['value' => AdditionalServiceEnum::LABELLESS->value, 'label' => __('Shipment without a label')],
            ['value' => AdditionalServiceEnum::HANDLE_WITH_CARE->value, 'label' => __('Handle with care')],
            ['value' => AdditionalServiceEnum::HAND_DELIVERY->value, 'label' => __('Hand delivery')],
            ['value' => AdditionalServiceEnum::CHECK_CONTENT->value, 'label' => __('Check of the contents of the package')],
            ['value' => AdditionalServiceEnum::PHONE_NOTIFICATION_RECIPIENT->value, 'label' => __('Phone call notification')],
            ['value' => AdditionalServiceEnum::COVER_ADDRESS_SENDER->value, 'label' => __('Different sender address on the cover')],
            ['value' => AdditionalServiceEnum::TIRES->value, 'label' => __('Tires')],
            ['value' => AdditionalServiceEnum::BRINGING_DELIVERED_PARCEL->value, 'label' => __('Bringing in of the delivery')],
            ['value' => AdditionalServiceEnum::SATURDAY_DELIVERY->value, 'label' => __('Saturday delivery')],
            ['value' => AdditionalServiceEnum::PHONE_NOTIFICATION_COLLECTION->value, 'label' => __('Phone call package collection confirmation')],
            ['value' => AdditionalServiceEnum::DELIVERY_TO_TIME->value, 'label' => __('Delivery within fixed hours')],
        ];
    }
}
