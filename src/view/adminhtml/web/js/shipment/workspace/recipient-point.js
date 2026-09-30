/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The recipient's pickup point, for carriers that deliver to points, chosen on the map.
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
            this.label = ko.observable('');
            this.required = ko.observable(false);
            this.missing = ko.observable(false);
            this.canChoose = ko.observable(false);
        },

        refresh: function () {
            const features = this.features();

            this.shown(features.point_allowed !== false);
            this.required(!!features.point_required);
            this.missing(this.required() && this.validated() && !rules.filled(this.get('recipient_point_id')));
            this.pointId(this.get('recipient_point_id') || '');
            // The address is only known for a point chosen on the map or the one from checkout.
            this.label(this.get('recipient_point_label_id') === this.pointId() ? this.get('recipient_point_label') || '' : '');
            this.canChoose(!!(this.get('map') || {}).token);
        },

        chooseOnMap: function () {
            const data = this.get() || {};

            pointsMap.open(
                data.map,
                this.features().providers,
                [data.recipient_postcode, data.recipient_city].filter(rules.filled).join(' '),
                function (pointId, point) {
                    this.set('recipient_point_id', pointId);
                    this.set('recipient_point_label', pointsMap.describe(point));
                    this.set('recipient_point_label_id', pointId);
                }.bind(this)
            );
        },

        edit: function () {
            this.openModal(this.recipientModal);
        },

        remove: function () {
            this.set('recipient_point_id', '');
            this.set('recipient_point_label', '');
        }
    });
});
