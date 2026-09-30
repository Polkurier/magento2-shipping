/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The recipient's pickup point: typed in, or chosen on the Polkurier map.
 */
define([
    'Magento_Ui/js/form/element/abstract',
    'Polkurier_Shipping/js/shipment/points-map'
], function (Abstract, pointsMap) {
    'use strict';

    return Abstract.extend({
        defaults: {
            elementTmpl: 'Polkurier_Shipping/shipment/point',
            required: false,
            imports: {
                required: '${ $.provider }:data.features.point_required'
            },
            listens: {
                required: 'requirePoint'
            }
        },

        /**
         * The carrier decides whether a point is required; it has to block placing, not just show
         * an asterisk. Checked on submit, not the moment the carrier changes.
         *
         * @param {Boolean} required
         */
        requirePoint: function (required) {
            this.validation['required-entry'] = !!required;
            if (!required) {
                this.error('');
            }
        },

        /**
         * @returns {Boolean}
         */
        canChoose: function () {
            return !!this.source.get('data.map.token');
        },

        chooseOnMap: function () {
            const features = this.source.get('data.features') || {};

            pointsMap.open(
                this.source.get('data.map'),
                features.providers,
                [this.source.get('data.recipient_postcode'), this.source.get('data.recipient_city')].filter(Boolean).join(' '),
                function (pointId, point) {
                    this.value(pointId);
                    this.source.set('data.recipient_point_label', pointsMap.describe(point));
                    this.source.set('data.recipient_point_label_id', pointId);
                }.bind(this)
            );
        }
    });
});
