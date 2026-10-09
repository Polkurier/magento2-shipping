/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * Sends the pickup point chosen for the selected method along with the shipping method.
 */
define([
    'mage/utils/wrapper',
    'Magento_Checkout/js/model/quote',
    'Polkurier_Shipping/js/model/pickup-point'
], function (wrapper, quote, pickupPoint) {
    'use strict';

    return function (payloadExtender) {
        return wrapper.wrap(payloadExtender, function (original, payload) {
            const method = quote.shippingMethod(),
                point = pickupPoint.isOwnMethod(method) ? pickupPoint.get(method.method_code) : null;

            original(payload);
            payload.addressInformation.extension_attributes = payload.addressInformation.extension_attributes || {};
            payload.addressInformation.extension_attributes.pickup_point_id = point ? point.id : null;

            return payload;
        });
    };
});
