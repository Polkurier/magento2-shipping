/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The point the parcels are handed over at, for carriers that take them at points: chosen on the
 * map, near the sender. It starts as the sender address's drop-off point.
 */
define([
    'ko',
    'mage/translate',
    './part',
    './rules',
    'Polkurier_Shipping/js/shipment/points-map'
], function (ko, $t, Part, rules, pointsMap) {
    'use strict';

    return Part.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/workspace/sender-point'
        },

        initState: function () {
            this.shown = ko.observable(false);
            this.pointId = ko.observable('');
            this.label = ko.observable('');
            this.required = ko.observable(false);
            this.missing = ko.observable(false);
            this.canChoose = ko.observable(false);
        },

        refresh: function () {
            const requirement = this.features().sender_point || 'NONE';

            this.shown(requirement !== 'NONE');
            this.required(requirement === 'REQUIRED');
            this.pointId(this.get('sender_point_id') || '');
            this.missing(this.required() && this.validated() && !rules.filled(this.pointId()));
            this.label(this.get('sender_point_label_id') === this.pointId() ? this.get('sender_point_label') || '' : '');
            this.canChoose(pointsMap.canOpen(this.get('map')));
        },

        /**
         * @returns {String} the sender's postcode, where the map starts
         */
        senderPostcode: function () {
            const data = this.get() || {},
                address = data.sender_address_id === 'new'
                    ? {postcode: data.new_sender_postcode}
                    : rules.filled(data.sender_address_id) ? (data.senders || {})[String(data.sender_address_id)] : data.store_sender;

            return address && rules.filled(address.postcode) ? String(address.postcode).trim() : '';
        },

        chooseOnMap: function () {
            pointsMap.open(this.get('map'), this.features().providers, this.senderPostcode(), function (pointId, point) {
                this.set('sender_point_id', pointId);
                this.set('sender_point_label', pointsMap.describe(point));
                this.set('sender_point_label_id', pointId);
            }.bind(this));
        },

        fix: function () {
            if (this.canChoose()) {
                this.chooseOnMap();
            }
        },

        remove: function () {
            this.set('sender_point_id', '');
        }
    });
});
