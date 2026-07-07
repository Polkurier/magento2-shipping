/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/model/shipping-save-processor/payload-extender': {
                'Polkurier_Shipping/js/model/shipping-save-processor/payload-extender-mixin': true
            },
            'Magento_Checkout/js/view/shipping': {
                'Polkurier_Shipping/js/view/shipping-validation-mixin': true
            }
        }
    }
};
