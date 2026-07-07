/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
define([
    'Magento_Checkout/js/model/quote',
    'mage/translate',
    'Magento_Ui/js/model/messageList'
], function (quote, $t, globalMessageList) {
    'use strict';

    return function (target) {
        return target.extend({
            validateShippingInformation() {
                const result = this._super();
                if (!result
                    || quote.shippingMethod().carrier_code !== 'polkurier'
                    || !window.checkoutConfig.polkurier.map.allowedMethods.includes(quote.shippingMethod().method_code)
                ) {
                    return result;
                }

                if (!quote.extensionAttributes
                    || !quote.extensionAttributes.polkurier_point
                ) {
                    globalMessageList.addErrorMessage({
                        message: $t('Please select a pickup point')
                    });

                    return false;
                }

                return true;
            }
        });
    };
});
