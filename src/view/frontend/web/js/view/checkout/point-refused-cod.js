/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * After the payment methods: why cash on delivery is missing, when the method's carrier collects it
 * but the chosen pickup point doesn't.
 */
define([
    'ko',
    'uiComponent',
    'mage/translate',
    'Magento_Checkout/js/model/quote',
    'Polkurier_Shipping/js/model/pickup-point'
], function (ko, Component, $t, quote, pickupPoint) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Polkurier_Shipping/payment/point-refused-cod'
        },

        note: $t("The pickup point you chose doesn't accept cash on delivery. To pay on delivery, choose another pickup point."),

        initialize: function () {
            this._super();

            this.visible = ko.pureComputed(function () {
                const method = quote.shippingMethod(),
                    point = method && pickupPoint.isOwnMethod(method) ? pickupPoint.get(method.method_code) : null;

                return !!point && pickupPoint.collectsCod(method.method_code) && point.cod === false;
            });

            return this;
        }
    });
});
