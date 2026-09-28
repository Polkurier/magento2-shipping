<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;

/**
 * Additional services that can be bought with a shipment.
 */
class AdditionalService extends AbstractOptions
{
    public function toArray(): array
    {
        return self::labels();
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            AdditionalServiceEnum::SmsNotificationRecipient->value => (string)\__('SMS notification when dispatched'),
            AdditionalServiceEnum::SmsNotificationRecipientWithName->value => (string)\__('SMS notification when dispatched, with the sender name'),
            AdditionalServiceEnum::SmsNotificationRecipientOutForDelivery->value => (string)\__('SMS notification when out for delivery'),
            AdditionalServiceEnum::SmsNotificationRecipientOutForDeliveryWithName->value => (string)\__('SMS notification when out for delivery, with the sender name'),
            AdditionalServiceEnum::PhoneNotificationRecipient->value => (string)\__('Phone notification before delivery'),
            AdditionalServiceEnum::PhoneNotificationCollection->value => (string)\__('Phone notification before collection'),
            AdditionalServiceEnum::CheckContent->value => (string)\__('Content check on delivery'),
            AdditionalServiceEnum::RefundDocs->value => (string)\__('Return of documents (ROD)'),
            AdditionalServiceEnum::CourierWithLabel->value => (string)\__('Courier brings the label'),
            AdditionalServiceEnum::Labelless->value => (string)\__('No label (shipment code)'),
            AdditionalServiceEnum::WeekCollection->value => (string)\__('Weekend collection'),
            AdditionalServiceEnum::SaturdayDelivery->value => (string)\__('Saturday delivery'),
            AdditionalServiceEnum::DeliveryToTime->value => (string)\__('Delivery by a given hour'),
            AdditionalServiceEnum::HandDelivery->value => (string)\__('Delivery to own hands'),
            AdditionalServiceEnum::HandleWithCare->value => (string)\__('Fragile'),
            AdditionalServiceEnum::CoverAddressSender->value => (string)\__('Cover address as sender'),
            AdditionalServiceEnum::Tires->value => (string)\__('Tires'),
            AdditionalServiceEnum::BringingDeliveredParcel->value => (string)\__('Bringing the parcel in'),
        ];
    }
}
