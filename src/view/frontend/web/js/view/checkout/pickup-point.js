/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * "Choose a pickup point" for each method that delivers to one, in the carrier's cell of the
 * method's row. Opening the map selects the method too, as a click on its row does.
 */
define([
    'uiComponent',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/action/select-shipping-method',
    'Magento_Checkout/js/checkout-data',
    'Polkurier_Shipping/js/model/pickup-point',
    'Polkurier_Shipping/js/model/points-map',
    'mage/translate'
], function (Component, quote, selectShippingMethod, checkoutData, pickupPoint, pointsMap, $t) {
    'use strict';

    return Component.extend({
        /**
         * @param {Object} method a shipping rate
         * @returns {Boolean}
         */
        hasMap: function (method) {
            // The token is fetched when the map opens, if the page has none.
            return pickupPoint.isOwnMethod(method) && pickupPoint.hasMap(method.method_code);
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
         * Selects the method, as a click on its row does.
         *
         * @param {Object} method a shipping rate
         * @returns {Boolean}
         */
        select: function (method) {
            selectShippingMethod(method);
            checkoutData.setSelectedShippingRate(method.carrier_code + '_' + method.method_code);

            return true;
        },

        /**
         * @param {Object} method a shipping rate
         */
        openMap: function (method) {
            const address = quote.shippingAddress(),
                methodCode = method.method_code;

            this.select(method);

            pointsMap.open(
                pickupPoint.config,
                pickupPoint.getProviders(methodCode),
                // The postcode alone: a city typed afterwards can't contradict it.
                address && address.postcode ? String(address.postcode).trim() : '',
                function (point) {
                    pickupPoint.set(methodCode, point);
                }
            );
        }
    });
});
