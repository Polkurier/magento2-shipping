/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * The payment step's "Ship Via" names the pickup point chosen for the method, as the order's
 * shipping description will.
 */
define([
    'Magento_Checkout/js/model/quote',
    'Polkurier_Shipping/js/model/pickup-point',
    'mage/translate'
], function (quote, pickupPoint, $t) {
    'use strict';

    return function (Component) {
        return Component.extend({
            /**
             * @returns {String}
             */
            getShippingMethodTitle: function () {
                const title = this._super(),
                    method = quote.shippingMethod(),
                    point = pickupPoint.isOwnMethod(method) ? pickupPoint.get(method.method_code) : null;

                if (!title || !point) {
                    return title;
                }

                return $t('%1, pickup point %2')
                    .replace('%1', title)
                    .replace('%2', point.label ? point.id + ' (' + point.label + ')' : point.id);
            }
        });
    };
});
