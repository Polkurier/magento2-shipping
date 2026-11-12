/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * The recipient's pickup point, for carriers that deliver to points, chosen on the map, or by its
 * code when there's no map.
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
            template: 'Polkurier_Shipping/shipment/workspace/recipient-point',
            // The form's modals, from its XML.
            recipientModal: ''
        },

        initState: function () {
            this.shown = ko.observable(false);
            this.pointId = ko.observable('');
            this.name = ko.observable('');
            this.label = ko.observable('');
            this.required = ko.observable(false);
            this.missing = ko.observable(false);
            this.canChoose = ko.observable(false);
            this.closed = ko.observable(false);
        },

        refresh: function () {
            const features = this.features(),
                // Known for points looked up when the screen opened and those chosen on the map.
                details = (this.get('point_details') || {})[this.get('recipient_point_id') || ''] || {};

            this.shown(features.point_allowed !== false);
            this.required(!!features.point_required);
            this.missing(this.required() && this.validated() && !rules.filled(this.get('recipient_point_id')));
            this.pointId(this.get('recipient_point_id') || '');
            this.name(details.name || '');
            this.label(details.label || '');
            this.canChoose(pointsMap.canOpen(this.get('map')));
            this.closed(rules.filled(this.pointId()) && this.get('recipient_point_closed') === this.pointId());
        },

        chooseOnMap: function () {
            const data = this.get() || {};

            pointsMap.open(
                data.map,
                this.features().providers,
                // The postcode alone: a city changed afterwards can't contradict it.
                rules.filled(data.recipient_postcode) ? String(data.recipient_postcode).trim() : '',
                function (pointId, point) {
                    this.set('point_details', pointsMap.addPoint(this.get('point_details'), pointId, point));
                    this.set('recipient_point_id', pointId);
                }.bind(this)
            );
        },

        edit: function () {
            this.openModal(this.recipientModal);
        },

        /**
         * On the map when there is one, or by typing the point's code.
         */
        change: function () {
            if (this.canChoose()) {
                this.chooseOnMap();
            } else {
                this.edit();
            }
        },

        /**
         * A missing point is chosen the same way as a point is changed.
         */
        fix: function () {
            this.change();
        },

        remove: function () {
            this.set('recipient_point_id', '');
        }
    });
});
