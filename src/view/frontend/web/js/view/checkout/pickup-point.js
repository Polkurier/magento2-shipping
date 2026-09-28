/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * "Choose a pickup point" next to each method that delivers to one.
 */
define([
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Polkurier_Shipping/js/model/pickup-point',
    'Polkurier_Shipping/js/model/points-map',
    'mage/translate'
], function (Component, quote, pickupPoint, pointsMap, $t) {
    'use strict';

    return Component.extend({
        /**
         * @param {Object} method a shipping rate
         * @returns {Boolean}
         */
        hasMap: function (method) {
            return pickupPoint.isOwnMethod(method) && pickupPoint.hasMap(method.method_code) && !!pickupPoint.config.token;
        },

        /**
         * @param {String} methodCode
         * @returns {String}
         */
        getPointLabel: function (methodCode) {
            const point = pickupPoint.get(methodCode);

            return point ? '[' + point.id + '] ' + point.label : $t('No pickup point chosen');
        },

        /**
         * @param {String} methodCode
         */
        openMap: function (methodCode) {
            const address = quote.shippingAddress();

            pointsMap.open(
                pickupPoint.config,
                pickupPoint.getProviders(methodCode),
                address ? [address.postcode, address.city].filter(Boolean).join(' ') : '',
                function (point) {
                    pickupPoint.set(methodCode, point);
                }
            );
        }
    });
});
