/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Saves the pickup point typed into the Polkurier box on the order page, or chosen on the map. The
 * point is checked with Polkurier; the answer's label and cash on delivery flag replace the ones
 * shown.
 */
define([
    'uiComponent',
    'jquery',
    'ko',
    'mage/translate',
    'Polkurier_Shipping/js/shipment/points-map'
], function (Component, $, ko, $t, pointsMap) {
    'use strict';

    return Component.extend({
        defaults: {
            provider: '${ $.ns }.${ $.ns }_data_source',
            editable: false,
            map: null,
            imports: {
                editable: '${ $.provider }:data.editable',
                map: '${ $.provider }:data.map'
            },
            tracks: {
                editable: true,
                map: true
            }
        },

        initialize: function () {
            this._super();
            this.saving = ko.observable(false);
            this.message = ko.observable('');
            this.failed = ko.observable(false);

            return this;
        },

        /**
         * The map opens on the order's carrier and shipping address. A point chosen there is
         * saved at once, as if typed in and saved.
         */
        chooseOnMap: function () {
            const map = this.map;

            pointsMap.open(map, map.providers, map.search || '', function (pointId) {
                this.source.set('data.point_id', pointId);
                this.save();
            }.bind(this));
        },

        save: function () {
            const data = this.source.get('data');

            this.saving(true);
            this.message('');
            $.ajax({
                url: data.urls.save,
                type: 'POST',
                dataType: 'json',
                data: {form_key: window.FORM_KEY, isAjax: true, order_id: data.order_id, point_id: data.point_id}
            }).done(function (response) {
                this.failed(!!response.error);
                this.message(response.message);
                if (!response.error) {
                    this.source.set('data.point_id', response.point_id);
                    this.source.set('data.point_label', response.point_label);
                    this.source.set('data.point_cod', response.point_cod);
                }
            }.bind(this)).fail(function () {
                this.failed(true);
                this.message($t('The pickup point could not be saved.'));
            }.bind(this)).always(function () {
                this.saving(false);
            }.bind(this));
        }
    });
});
