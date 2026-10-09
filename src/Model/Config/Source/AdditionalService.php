<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;

/**
 * Additional services that can be bought with a shipment, to choose from. The cover sender address
 * service isn't offered as a checkbox: it comes with the address chosen to print as the sender (the
 * shipment's cover address). Polkurier needs that address for the service, and switches the service
 * on by it. labels() still includes it, to name it in what was sent.
 */
readonly class AdditionalService extends AbstractOptions
{
    public function toArray(): array
    {
        $labels = self::labels();
        unset($labels[AdditionalServiceEnum::CoverSenderAddress->value]);

        return $labels;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            AdditionalServiceEnum::SmsOnDispatch->value => (string)__('SMS notification when dispatched'),
            AdditionalServiceEnum::SmsOnDispatchWithSenderName->value
                => (string)__('SMS notification when dispatched, with the sender name'),
            AdditionalServiceEnum::SmsOutForDelivery->value => (string)__('SMS notification when out for delivery'),
            AdditionalServiceEnum::SmsOutForDeliveryWithSenderName->value
                => (string)__('SMS notification when out for delivery, with the sender name'),
            AdditionalServiceEnum::PhoneCallBeforeDelivery->value => (string)__('Phone call before delivery'),
            AdditionalServiceEnum::PhoneCallBeforePickup->value => (string)__('Phone call before collection'),
            AdditionalServiceEnum::CheckContent->value => (string)__('Contents checked by the recipient'),
            AdditionalServiceEnum::ReturnOfDocuments->value => (string)__('Return of documents (ROD)'),
            AdditionalServiceEnum::CourierBringsLabel->value => (string)__('Courier brings the label'),
            AdditionalServiceEnum::Labelless->value => (string)__('No label (shipment code)'),
            AdditionalServiceEnum::WeekendDelivery->value => (string)__('Weekend delivery'),
            AdditionalServiceEnum::SaturdayDelivery->value => (string)__('Saturday delivery'),
            AdditionalServiceEnum::DeliveryByTime->value => (string)__('Delivery by a set time'),
            AdditionalServiceEnum::HandDelivery->value => (string)__('Hand delivery'),
            AdditionalServiceEnum::HandleWithCare->value => (string)__('Fragile'),
            AdditionalServiceEnum::CoverSenderAddress->value => (string)__('Another sender on the label'),
            AdditionalServiceEnum::Tires->value => (string)__('Tires'),
            AdditionalServiceEnum::CarryIn->value => (string)__('Carried in to the door'),
            AdditionalServiceEnum::DangerousGoods->value => (string)__('Dangerous goods (ADR)'),
            AdditionalServiceEnum::DoNotStack->value => (string)__('Do not stack'),
            AdditionalServiceEnum::TailLiftAtPickup->value => (string)__('Tail lift at collection'),
            AdditionalServiceEnum::TailLiftAtDelivery->value => (string)__('Tail lift at delivery'),
            AdditionalServiceEnum::SideLoadAtPickup->value => (string)__('Side loading at collection'),
            AdditionalServiceEnum::SideUnloadAtDelivery->value => (string)__('Side unloading at delivery'),
        ];
    }
}
