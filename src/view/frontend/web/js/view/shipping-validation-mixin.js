/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Stops at the shipping step when the selected method needs a pickup point and none is
 * chosen for it. The server checks again when the order is placed.
 */
define([
    'Magento_Checkout/js/model/quote',
    'Magento_Ui/js/model/messageList',
    'Polkurier_Shipping/js/model/pickup-point',
    'mage/translate'
], function (quote, messageList, pickupPoint, $t) {
    'use strict';

    return function (Shipping) {
        return Shipping.extend({
            /**
             * @returns {Boolean}
             */
            validateShippingInformation: function () {
                const method = quote.shippingMethod();

                if (pickupPoint.isOwnMethod(method)
                    && pickupPoint.isRequired(method.method_code)
                    && !pickupPoint.get(method.method_code)
                ) {
                    messageList.addErrorMessage({message: $t('Choose a pickup point for the selected delivery method.')});

                    return false;
                }

                return this._super();
            }
        });
    };
});
