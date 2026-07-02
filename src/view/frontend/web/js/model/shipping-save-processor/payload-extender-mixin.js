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
            originalPayloadExtender(payload);
            if (quote.extension_attributes && quote.extension_attributes.polkurier_point) {
                _.extend(
                    payload.addressInformation.extension_attributes,
                    {
                        polkurier_point_id: quote.extension_attributes.polkurier_point.id,
                        polkurier_point_cod: quote.extension_attributes.polkurier_point.cod
                    }
                );
            }
        });
    };
});
