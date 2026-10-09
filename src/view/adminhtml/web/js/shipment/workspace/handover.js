/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * How the parcels get to the carrier: a courier collecting them, on a date and in a window read
 * from Polkurier; dropping them off at a point; or a collective pickup booked later. The dates
 * are read again whenever the carrier, the sender or the shipment changes, and the first free
 * one is taken until the merchant picks another. A collective pickup is offered unless Polkurier
 * says the carrier can't collect these parcels that way; it's asked again whenever they change.
 *
 * When the parcels are dropped off with a carrier that takes them at points, the point is chosen
 * on the map, near the sender. It starts as the sender address's drop-off point.
 */
define([
    'ko',
    'underscore',
    'mage/translate',
    './part',
    './rules',
    'Polkurier_Shipping/js/request',
    'Polkurier_Shipping/js/shipment/points-map'
], function (ko, _, $t, Part, rules, request, pointsMap) {
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
            // Offers "Try again" when asking again may help, but not after a refusal, which would
            // repeat.
            this.retryable = ko.observable(false);
            this.dateMissing = ko.observable(false);
            this.windowMissing = ko.observable(false);
            this.datesKey = null;
            // Only the answer to the latest request for collection dates counts.
            this.dates = request();
            // Whether Polkurier can collect the parcels with others: null until it says.
            this.collective = ko.observable(null);
            this.collectiveKey = null;
            this.collectiveRequest = request();
            this.scheduleCollective = _.debounce(this.askCollective.bind(this), 600);
            this.scheduleLoad = _.debounce(this.load.bind(this), 400);
            // The drop-off point: under "dropped off", or on its own with a carrier that takes the
            // parcels only at points but not as dropped off.
            this.pointShown = ko.observable(false);
            this.pointAlone = ko.observable(false);
            this.pointId = ko.observable('');
            this.pointLabel = ko.observable('');
            this.pointRequired = ko.observable(false);
            this.pointMissing = ko.observable(false);
            this.canChoosePoint = ko.observable(false);
            this.mode.subscribe(function (mode) {
                if (mode && mode !== this.get('pickup_mode')) {
                    this.set('pickup_mode', mode);
                    // Collected from the sender: no drop-off point, which would show here unused.
                    if (mode !== 'drop_off' && rules.filled(this.get('sender_point_id'))) {
                        this.set('sender_point_id', '');
                    }
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

            this.watchCollective(data);
            this.followSenderAndCarrier(data, features);
            _.each(labels, function (label, value) {
                if (rules.handoverOffered(value, features, this.collective())) {
                    modes.push({value: value, label: label, id: 'polkurier-handover-' + value});
                }
            }, this);
            this.modes(modes);
            if (features.sender_point === 'REQUIRED' && data.pickup_mode !== 'drop_off') {
                // A carrier that takes parcels only at points: no courier collects them.
                this.set('pickup_mode', 'drop_off');

                return;
            }
            if (data.pickup_mode === 'multi' && this.collective() === false) {
                // No longer possible for these parcels: a courier collects them, as by default.
                this.set('pickup_mode', 'courier');

                return;
            }
            this.mode(data.pickup_mode || 'courier');
            this.refreshPoint(data, features);
            this.showDates(this.mode() === 'courier' && features.pickup_date !== false);
            this.dateMissing(this.validated() && this.showDates() && Boolean(features.pickup_date_required)
                && !rules.filled(data.pickup_date));
            this.windowMissing(this.validated() && this.showDates() && Boolean(features.pickup_time_required)
                && !(rules.filled(data.pickup_time_from) && rules.filled(data.pickup_time_to)));
            if (this.showDates()) {
                const key = JSON.stringify(_.pick(
                    data,
                    'carrier_code',
                    'sender_address_id',
                    'new_sender_postcode',
                    'shipment_type',
                    'recipient_postcode'
                ));

                if (key !== this.datesKey) {
                    this.datesKey = key;
                    this.scheduleLoad();
                }
            } else if (this.mode() === 'courier' && features.pickup_date === false) {
                // A carrier that takes no collection date: a date chosen for another carrier would
                // go unseen.
                this.datesKey = null;
                this.days([]);
                this.forgetDate();
            }
        },

        /**
         * Another sender or carrier starts from the default handover again (rules.defaultHandover()).
         * A drop-off point chosen or removed here keeps the handover as it is.
         *
         * @param {Object} data
         * @param {Object} features
         */
        followSenderAndCarrier: function (data, features) {
            // The sender and carrier pickers set the sender's point along with the sender or the
            // carrier, before the debounced refresh reads it.
            const key = [data.carrier_code, data.sender_address_id].map(function (value) {
                return String(value === null || value === undefined ? '' : value);
            }).join('|');

            if (this.senderAndCarrier !== undefined && this.senderAndCarrier !== key) {
                const mode = rules.defaultHandover(features, String(data.sender_point_id || ''));

                if (mode !== data.pickup_mode) {
                    this.senderAndCarrier = key;
                    this.set('pickup_mode', mode);

                    return;
                }
            }
            this.senderAndCarrier = key;
        },

        retry: function () {
            this.load();
        },

        /**
         * @param {Object} data
         * @param {Object} features
         */
        refreshPoint: function (data, features) {
            const requirement = features.sender_point || 'NONE';

            this.pointShown(rules.senderPointUsed(requirement, features.drop_off, this.mode()));
            this.pointAlone(this.pointShown() && this.mode() !== 'drop_off');
            this.pointRequired(requirement === 'REQUIRED');
            this.pointId(data.sender_point_id || '');
            this.pointMissing(this.pointRequired() && this.validated() && !rules.filled(this.pointId()));
            this.pointLabel(data.sender_point_label_id === this.pointId() ? data.sender_point_label || '' : '');
            this.canChoosePoint(pointsMap.canOpen(data.map));
        },

        /**
         * @returns {String} the sender's postcode, where the map starts
         */
        senderPostcode: function () {
            const data = this.get() || {},
                address = data.sender_address_id === 'new'
                    ? {postcode: data.new_sender_postcode}
                    : rules.filled(data.sender_address_id)
                        ? (data.senders || {})[String(data.sender_address_id)]
                        : data.store_sender;

            return address && rules.filled(address.postcode) ? String(address.postcode).trim() : '';
        },

        choosePoint: function () {
            pointsMap.open(
                this.get('map'),
                this.features().providers,
                this.senderPostcode(),
                function (pointId, point) {
                    this.set('sender_point_id', pointId);
                    this.set('sender_point_label', pointsMap.describe(point));
                    this.set('sender_point_label_id', pointId);
                }.bind(this),
                ['send']
            );
        },

        removePoint: function () {
            this.set('sender_point_id', '');
        },

        fix: function () {
            if (this.pointShown() && this.canChoosePoint()) {
                this.choosePoint();
            }
        },

        /**
         * Asks again whenever anything Polkurier's answer depends on changes.
         *
         * @param {Object} data
         */
        watchCollective: function (data) {
            const key = JSON.stringify([
                _.pick(data, 'carrier_code', 'sender_address_id', 'new_sender_postcode', 'shipment_type'),
                (data.packs || []).map(function (pack) {
                    return _.pick(pack, 'pack_type', 'length', 'width', 'height', 'weight', 'amount');
                })
            ]);

            if (key !== this.collectiveKey) {
                this.collectiveKey = key;
                this.scheduleCollective();
            }
        },

        askCollective: function () {
            this.collectiveRequest.post(this.get('urls.collective_pickup'), {
                data: _.pick(
                    this.get(),
                    'order_id',
                    'carrier_code',
                    'sender_address_id',
                    'new_sender_postcode',
                    'shipment_type',
                    'packs'
                )
            }, {
                done: function (response) {
                    // Offered when unknown or when Polkurier couldn't answer: it says so when placing.
                    this.collective(
                        !response.error && typeof response.available === 'boolean' ? response.available : null
                    );
                    this.refresh();
                }.bind(this),
                fail: function () {
                    // Offered anyway: if Polkurier can't take it, it says so when placing.
                    this.collective(null);
                    this.refresh();
                }.bind(this)
            });
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
                data: _.pick(
                    this.get(),
                    'order_id',
                    'carrier_code',
                    'sender_address_id',
                    'new_sender_postcode',
                    'shipment_type',
                    'recipient_postcode'
                )
            }, {
                done: function (response) {
                    this.days((response.days || []).map(function (day) {
                        return {value: day.date, label: day.date, windows: day.windows || []};
                    }));
                    this.message(
                        response.error
                            ? response.message
                            : (this.days().length ? '' : $t('No collection dates available.'))
                    );
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
                // A day without windows: the carrier comes at any time that day, and no window
                // stays chosen.
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
