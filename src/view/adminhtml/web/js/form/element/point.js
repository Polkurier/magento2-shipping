/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */

/**
 * A drop-off point's code, typed in or chosen on the pickup point map among the points that take
 * parcels for sending, shown with its name and address when they're known.
 * The map, where its token comes from, the providers it shows and the postcode it starts at come
 * with the field's config; without a map, the code can only be typed in.
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
            providers: [],
            postcode: '',
            // The names and addresses of the points known so far, by code.
            pointDetails: {},
            details: '',
            listens: {
                value: 'describe'
            }
        },

        initialize: function () {
            this._super();
            this.describe(this.value());

            return this;
        },

        initObservable: function () {
            this._super().observe(['details']);

            return this;
        },

        /**
         * @param {String} value
         */
        describe: function (value) {
            this.details(pointsMap.describe(this.pointDetails, String(value || '').trim()));
        },

        /**
         * @returns {Boolean}
         */
        canChoose: function () {
            return pointsMap.canOpen(this.map);
        },

        choose: function () {
            pointsMap.open(this.map, this.providers, this.postcode, function (pointId, point) {
                this.pointDetails = pointsMap.addPoint(this.pointDetails, pointId, point);
                this.value(pointId);
            }.bind(this), ['send']);
        },

        remove: function () {
            this.value('');
        }
    });
});
