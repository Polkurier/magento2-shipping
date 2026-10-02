/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Magento's packaging window on the shipment pages of a Polkurier order, with the label options
 * or without them (a shipment whose Polkurier order stands): pack templates, kilograms and
 * centimetres, see ./label-options/packaging.
 */
define(['./label-options/packaging'], function (packaging) {
    'use strict';

    return function (config) {
        packaging.init(config.templates || [], config.parcelWeight || 'products', config.autoPack || null);
    };
});
