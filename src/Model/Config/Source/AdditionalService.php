<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Webservice\Api\Data\AdditionalServiceEnum;

class AdditionalService implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => AdditionalServiceEnum::REFUND_DOCS, 'label' => __('Refund documents')],
            ['value' => AdditionalServiceEnum::COURIER_WITH_LABEL, 'label' => __('Courier with printed out documents')],
            ['value' => AdditionalServiceEnum::WEEK_COLLECTION, 'label' => __('Weekend delivery')],
            ['value' => AdditionalServiceEnum::SMS_NOTIFICATION_RECIPIENT, 'label' => __('SMS notifications')],
            ['value' => AdditionalServiceEnum::SMS_NOTIFICATION_RECIPIENT_WITH_NAME, 'label' => __('SMS notifications with a custom name')],
            ['value' => AdditionalServiceEnum::LABELLESS, 'label' => __('Shipment without a label')],
            ['value' => AdditionalServiceEnum::HANDLE_WITH_CARE, 'label' => __('Handle with care')],
            ['value' => AdditionalServiceEnum::HAND_DELIVERY, 'label' => __('Hand delivery')],
            ['value' => AdditionalServiceEnum::CHECK_CONTENT, 'label' => __('Check of the contents of the package')],
            ['value' => AdditionalServiceEnum::PHONE_NOTIFICATION_RECIPIENT, 'label' => __('Phone call notification')],
            ['value' => AdditionalServiceEnum::COVER_ADDRESS_SENDER, 'label' => __('Different sender address on the cover')],
            ['value' => AdditionalServiceEnum::TIRES, 'label' => __('Tires')],
            ['value' => AdditionalServiceEnum::BRINGING_DELIVERED_PARCEL, 'label' => __('Bringing in of the delivery')],
            ['value' => AdditionalServiceEnum::SATURDAY_DELIVERY, 'label' => __('Saturday delivery')],
            ['value' => AdditionalServiceEnum::PHONE_NOTIFICATION_COLLECTION, 'label' => __('Phone call package collection confirmation')],
            ['value' => AdditionalServiceEnum::DELIVERY_TO_TIME, 'label' => __('Delivery within fixed hours')],
        ];
    }
}
