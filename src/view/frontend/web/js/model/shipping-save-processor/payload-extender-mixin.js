/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
define([
    'underscore',
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote'
], function (_, wrapper, quote) {
    'use strict';

    return function (payloadExtender) {
        return wrapper.wrap(payloadExtender, function (originalPayloadExtender, payload) {
            const shippingMethod = quote.shippingMethod();
            originalPayloadExtender(payload);

            if (shippingMethod.carrier_code === 'polkurier'
                && quote.extensionAttributes
                && quote.extensionAttributes.polkurier_point
                && quote.extensionAttributes.polkurier_point[shippingMethod.method_code]
            ) {
                _.extend(
                    payload.addressInformation.extension_attributes,
                    {
                        polkurier_point_id: quote.extensionAttributes.polkurier_point[shippingMethod.method_code].id,
                        polkurier_point_cod: quote.extensionAttributes.polkurier_point[shippingMethod.method_code].cod
                    }
                );
            }
        });
    };
});
