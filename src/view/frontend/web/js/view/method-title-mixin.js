/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Magento titles a chosen method "carrier - method". Methods offered without a carrier title are
 * shown by their own label alone, without a dangling separator.
 */
define(['Magento_Checkout/js/model/quote'], function (quote) {
    'use strict';

    return function (Component) {
        return Component.extend({
            /**
             * @returns {String}
             */
            getShippingMethodTitle: function () {
                const method = quote.shippingMethod();

                if (method && !method.carrier_title && method.method_title) {
                    return this.isCalculated && !this.isCalculated() ? '' : method.method_title;
                }

                return this._super();
            }
        });
    };
});
