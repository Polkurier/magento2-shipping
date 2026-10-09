/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */

/**
 * A drop-off point's id, typed in or chosen on the pickup point map, which starts at the form's
 * postcode and shows only points that take parcels for sending.
 * The map, where its token comes from, and the providers it shows come with the field's config;
 * without a map, the id can only be typed in.
 */
define([
    'Magento_Ui/js/form/element/abstract',
    'Polkurier_Shipping/js/shipment/points-map'
], function (Abstract, pointsMap) {
    'use strict';

    return Abstract.extend({
        defaults: {
            elementTmpl: 'Polkurier_Shipping/form/element/point',
            map: {},
            providers: []
        },

        /**
         * @returns {Boolean}
         */
        canChoose: function () {
            return pointsMap.canOpen(this.map);
        },

        choose: function () {
            const postcode = String(this.source.get('data.postcode') || '').trim();

            // Points that take parcels for sending, of every carrier that takes them at points.
            pointsMap.open(this.map, this.providers, postcode, function (pointId) {
                this.value(pointId);
            }.bind(this), ['send']);
        }
    });
});
