/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The carrier select. Publishes what the chosen carrier supports as data.features, which the rest
 * of the form imports to show, hide and narrow its fields.
 *
 * A pickup point belongs to the carrier it was chosen for: changing the carrier puts it aside, and
 * going back to that carrier brings it back.
 */
define([
    'Magento_Ui/js/form/element/select',
    'Polkurier_Shipping/js/shipment/workspace/rules'
], function (Select, rules) {
    'use strict';

    return Select.extend({
        defaults: {
            listens: {
                value: 'publishFeatures'
            }
        },

        initialize: function () {
            this._super();
            this.publishFeatures(this.value());

            return this;
        },

        /**
         * @param {String} previous
         * @param {String} code
         */
        swapPoint: function (previous, code) {
            const point = rules.swapPoint(this.pointsByCarrier, previous, code, {
                id: this.source.get('data.recipient_point_id') || '',
                label: this.source.get('data.recipient_point_label_id') === this.source.get('data.recipient_point_id')
                    ? this.source.get('data.recipient_point_label') || ''
                    : ''
            });

            this.source.set('data.recipient_point_id', point.id);
            this.source.set('data.recipient_point_label', point.label);
            this.source.set('data.recipient_point_label_id', point.id);
        },

        /**
         * @returns {String} the drop-off point of the chosen sender address, '' without one
         */
        senderAddressPoint: function () {
            const data = this.source.get('data') || {},
                address = (data.senders || {})[String(data.sender_address_id || '')];

            return address && address.point_id ? String(address.point_id) : '';
        },

        /**
         * @param {String} code
         */
        publishFeatures: function (code) {
            // Also called while the field is set up, with the carrier the form starts with.
            if (this.pointsByCarrier === undefined) {
                this.pointsByCarrier = {};
                this.carrier = code;
            } else if (code !== this.carrier) {
                this.swapPoint(this.carrier, code);
                this.carrier = code;
                // Points are the carrier's own: the sender's goes back to its address's.
                this.source.set('data.sender_point_id', this.senderAddressPoint());
            }
            const carriers = this.source.get('data.carriers') || {},
                carrier = carriers[code] || null;

            this.source.set('data.features', rules.featuresOf(carrier));
            // Parcels can't be dropped off with a carrier that only collects them, and are with one
            // that takes them only at points.
            if (carrier && !carrier.drop_off && this.source.get('data.pickup_mode') === 'drop_off') {
                this.source.set('data.pickup_mode', 'courier');
            }
            if (carrier && carrier.sender_point === 'REQUIRED' && carrier.drop_off) {
                this.source.set('data.pickup_mode', 'drop_off');
            }
        }
    });
});
