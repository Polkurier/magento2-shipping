/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Asks Polkurier when the carrier can collect, and fills the date and window from a choice.
 */
define([
    'uiComponent',
    'jquery',
    'ko',
    'mage/translate'
], function (Component, $, ko, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            mode: 'courier',
            imports: {
                mode: '${ $.provider }:data.pickup_mode'
            },
            listens: {
                mode: 'updateVisibility'
            }
        },

        initialize: function () {
            this._super();
            this.days = ko.observableArray([]);
            this.loading = ko.observable(false);
            this.error = ko.observable('');
            this.visible = ko.observable(this.mode === 'courier');

            return this;
        },

        updateVisibility: function () {
            if (this.visible) {
                this.visible(this.mode === 'courier');
            }
        },

        load: function () {
            const source = this.source;

            this.loading(true);
            this.error('');
            $.ajax({
                url: source.get('data.urls.pickup_times'),
                type: 'POST',
                dataType: 'json',
                data: {form_key: window.FORM_KEY, data: source.get('data')}
            }).done(function (response) {
                this.days((response.days || []).map(function (day) {
                    return {
                        date: day.date,
                        windows: (day.windows || []).map(function (window) {
                            return {
                                label: window.from + '–' + window.to,
                                choose: this.choose.bind(this, day.date, window)
                            };
                        }, this)
                    };
                }, this));
                this.error(response.error ? response.message : (response.days && response.days.length ? '' : $t('No collection dates available.')));
            }.bind(this)).fail(function () {
                this.error($t('The collection dates could not be read.'));
            }.bind(this)).always(function () {
                this.loading(false);
            }.bind(this));
        },

        /**
         * @param {String} date
         * @param {{from: String, to: String}} window
         */
        choose: function (date, window) {
            this.source.set('data.pickup_date', date);
            this.source.set('data.pickup_time_from', window.from);
            this.source.set('data.pickup_time_to', window.to);
        }
    });
});
