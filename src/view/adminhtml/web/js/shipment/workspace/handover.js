/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * How the parcels get to the carrier: a courier collecting them, on a date and in a window read
 * from Polkurier; dropping them off at a point; or a collective pickup booked later. The dates
 * are read again whenever the carrier, the sender or the shipment changes, and the first free
 * one is taken until the merchant picks another.
 */
define([
    'ko',
    'underscore',
    'mage/translate',
    './part',
    './rules',
    'Polkurier_Shipping/js/request'
], function (ko, _, $t, Part, rules, request) {
    'use strict';

    return Part.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/workspace/handover'
        },

        initState: function () {
            this.modes = ko.observableArray([]);
            this.mode = ko.observable('');
            this.showDates = ko.observable(false);
            this.days = ko.observableArray([]);
            this.windows = ko.observableArray([]);
            this.date = ko.observable('');
            this.window = ko.observable('');
            this.loading = ko.observable(false);
            this.message = ko.observable('');
            // A failure, in red; otherwise a muted note.
            this.failed = ko.observable(false);
            // With "Try again" when asking again may help: not after a refusal, which would repeat.
            this.retryable = ko.observable(false);
            this.dateMissing = ko.observable(false);
            this.windowMissing = ko.observable(false);
            this.datesKey = null;
            // Only the answer to the latest request for collection dates counts.
            this.dates = request();
            this.scheduleLoad = _.debounce(this.load.bind(this), 400);
            this.mode.subscribe(function (mode) {
                if (mode && mode !== this.get('pickup_mode')) {
                    this.set('pickup_mode', mode);
                }
            }, this);
            this.date.subscribe(function (date) {
                if (date && date !== this.get('pickup_date')) {
                    this.set('pickup_date', date);
                    this.chooseWindow();
                }
            }, this);
            this.window.subscribe(function (value) {
                const parts = (value || '').split('|');

                if (value && (parts[0] !== this.get('pickup_time_from') || parts[1] !== this.get('pickup_time_to'))) {
                    this.set('pickup_time_from', parts[0]);
                    this.set('pickup_time_to', parts[1]);
                }
            }, this);
        },

        refresh: function () {
            const data = this.get() || {},
                features = this.features(),
                labels = (data.choices || {}).pickup_mode || {},
                modes = [];

            _.each(labels, function (label, value) {
                if (value !== 'drop_off' || features.drop_off !== false) {
                    modes.push({value: value, label: label, id: 'polkurier-handover-' + value});
                }
            });
            this.modes(modes);
            this.mode(data.pickup_mode || 'courier');
            this.showDates(this.mode() === 'courier' && features.pickup_date !== false);
            this.dateMissing(this.validated() && this.showDates() && Boolean(features.pickup_date_required) && !rules.filled(data.pickup_date));
            this.windowMissing(this.validated() && this.showDates() && Boolean(features.pickup_time_required)
                && !(rules.filled(data.pickup_time_from) && rules.filled(data.pickup_time_to)));
            if (this.showDates()) {
                const key = JSON.stringify(_.pick(data, 'carrier_code', 'sender_address_id', 'shipment_type', 'recipient_postcode'));

                if (key !== this.datesKey) {
                    this.datesKey = key;
                    this.scheduleLoad();
                }
            }
        },

        retry: function () {
            this.load();
        },

        load: function () {
            this.failed(false);
            this.retryable(false);
            if (!rules.filled(this.get('carrier_code'))) {
                this.dates.cancel();
                this.days([]);
                this.forgetDate();
                this.loading(false);
                this.message($t('Choose a carrier to see its collection dates.'));

                return;
            }
            this.loading(true);
            this.message('');
            this.dates.post(this.get('urls.pickup_times'), {
                data: _.pick(this.get(), 'order_id', 'carrier_code', 'sender_address_id', 'shipment_type', 'recipient_postcode')
            }, {
                done: function (response) {
                    this.days((response.days || []).map(function (day) {
                        return {value: day.date, label: day.date, windows: day.windows || []};
                    }));
                    this.message(response.error ? response.message : (this.days().length ? '' : $t('No collection dates available.')));
                    this.failed(Boolean(response.error));
                    this.retryable(Boolean(response.error && response.retry));
                    this.chooseDate();
                }.bind(this),
                fail: function () {
                    // Not read again by itself with every change, only when asked to.
                    this.message($t('The collection dates could not be read.'));
                    this.failed(true);
                    this.retryable(true);
                }.bind(this),
                always: function () {
                    this.loading(false);
                }.bind(this)
            });
        },

        /**
         * Keeps the chosen date if it's still offered, or takes the first one.
         */
        chooseDate: function () {
            const days = this.days(),
                current = this.get('pickup_date'),
                day = _.findWhere(days, {value: current}) || days[0];

            if (!day) {
                this.forgetDate();

                return;
            }
            if (day.value !== current) {
                this.set('pickup_date', day.value);
            }
            this.date(day.value);
            this.chooseWindow();
        },

        /**
         * Without dates to choose from, none stays chosen: an earlier one would go unseen.
         */
        forgetDate: function () {
            this.windows([]);
            this.date('');
            this.window('');
            ['pickup_date', 'pickup_time_from', 'pickup_time_to'].forEach(function (field) {
                if (rules.filled(this.get(field))) {
                    this.set(field, '');
                }
            }, this);
        },

        /**
         * Keeps the chosen window if the date still has it, or takes the date's first one.
         */
        chooseWindow: function () {
            const day = _.findWhere(this.days(), {value: this.get('pickup_date')}),
                windows = day ? day.windows.map(function (window) {
                    return {value: window.from + '|' + window.to, label: window.from + '–' + window.to};
                }) : [],
                current = this.get('pickup_time_from') + '|' + this.get('pickup_time_to'),
                chosen = _.findWhere(windows, {value: current}) || windows[0];

            this.windows(windows);
            if (chosen) {
                if (chosen.value !== current) {
                    const parts = chosen.value.split('|');

                    this.set('pickup_time_from', parts[0]);
                    this.set('pickup_time_to', parts[1]);
                }
                this.window(chosen.value);
            } else {
                // A day without windows: the carrier comes when it comes, and none stays chosen.
                this.window('');
                ['pickup_time_from', 'pickup_time_to'].forEach(function (field) {
                    if (rules.filled(this.get(field))) {
                        this.set(field, '');
                    }
                }, this);
            }
        }
    });
});
