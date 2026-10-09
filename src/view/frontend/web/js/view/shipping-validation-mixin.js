/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * Stops at the shipping step when the selected method needs a pickup point and none is
 * chosen for it, saying so above the button as Magento does for a missing method. The server
 * checks again when the order is placed.
 */
define([
    'Magento_Checkout/js/model/quote',
    'Polkurier_Shipping/js/model/pickup-point',
    'mage/translate'
], function (quote, pickupPoint, $t) {
    'use strict';

    return function (Shipping) {
        return Shipping.extend({
            initialize: function () {
                this._super();
                // Magento clears it when the method changes; choosing a point clears it too.
                pickupPoint.onChange(function () {
                    this.errorValidationMessage(false);
                }.bind(this));

                return this;
            },

            /**
             * @returns {Boolean}
             */
            validateShippingInformation: function () {
                const method = quote.shippingMethod();

                if (pickupPoint.isOwnMethod(method)
                    && pickupPoint.isRequired(method.method_code)
                    && !pickupPoint.get(method.method_code)
                ) {
                    this.errorValidationMessage($t('Choose a pickup point for the selected delivery method.'));

                    return false;
                }

                return this._super();
            }
        });
    };
});
