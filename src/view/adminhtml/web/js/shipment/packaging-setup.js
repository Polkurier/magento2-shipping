/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * Sets up Magento's packaging window on the shipment pages of a Polkurier order, with or without
 * the label options (without them for a shipment whose Polkurier order is still active): pack
 * templates, kilograms and centimetres. See ./label-options/packaging.
 */
define(['./label-options/packaging'], function (packaging) {
    'use strict';

    return function (config) {
        packaging.init(config.templates || [], config.parcelWeight || 'products', config.autoPack || null);
    };
});
