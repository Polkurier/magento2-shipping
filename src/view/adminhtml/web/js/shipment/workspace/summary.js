/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The side panel of the shipment screen: the price with the chosen carrier, what still needs
 * attention, and the button that places the shipment.
 *
 * Whenever something that affects the price changes, it asks Polkurier again, once things settle,
 * and publishes every carrier's price as the provider's "quotes" for the carrier list. When the
 * form turns out invalid, it opens the modal holding the first invalid field.
 */
define([
    'uiComponent',
    'uiRegistry',
    'jquery',
    'ko',
    'underscore',
    'mage/translate',
    './rules'
], function (Component, registry, $, ko, _, $t, rules) {
    'use strict';

    const DRAFT_FIELDS = [
        'order_id', 'carrier_code', 'shipment_type', 'description', 'sender_address_id',
        'recipient_person', 'recipient_company', 'recipient_street', 'recipient_house_number',
        'recipient_flat_number', 'recipient_postcode', 'recipient_city', 'recipient_country',
        'recipient_phone', 'recipient_email', 'recipient_point_id', 'packs', 'cod_amount', 'cod_type',
        'cod_return_type', 'cod_bank_account_id', 'insurance_amount', 'cover_address_id', 'services',
        'pickup_mode', 'pickup_date', 'pickup_time_from', 'pickup_time_to', 'additional_fields'
    ];

    const MODALS = {
        recipient: 'recipient_modal',
        shipment: 'shipment_modal',
        handover: 'handover_modal',
        cod: 'cod_modal',
        extras: 'extras_modal',
        fields: 'fields_modal'
    };

    return Component.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/workspace/summary',
            provider: '${ $.ns }.${ $.ns }_data_source',
            formName: '${ $.ns }.${ $.ns }',
            quoteDelay: 600,
            invalid: false,
            imports: {
                invalid: '${ $.provider }:params.invalid'
            },
            listens: {
                invalid: 'onInvalid'
            }
        },

        initialize: function () {
            this._super();
            this.price = ko.observable(null);
            this.message = ko.observable('');
            this.quoting = ko.observable(false);
            this.issues = ko.observableArray([]);
            this.carrierName = ko.observable('');
            this.cheaper = ko.observable(null);
            this.lastPayload = null;
            this.requestNumber = 0;
            this.scheduleQuote = _.debounce(this.quote.bind(this), this.quoteDelay);
            this.source.on('data', _.debounce(this.onChange.bind(this), 30));
            this.onChange();

            return this;
        },

        onChange: function () {
            this.refresh();
            this.scheduleQuote();
        },

        refresh: function () {
            const data = this.source.get('data') || {},
                quotes = this.source.get('quotes') || null,
                carriers = data.carriers || {},
                chosen = quotes ? quotes[data.carrier_code] || null : null,
                currency = data.currency || 'PLN';
            let best = null;

            this.carrierName(data.carrier_code ? (carriers[data.carrier_code] || {}).name || data.carrier_code : '');
            this.issues(rules.issues(data, quotes));
            this.price(chosen ? {
                gross: chosen.gross.toFixed(2) + ' ' + currency,
                net: chosen.net.toFixed(2) + ' ' + currency
            } : null);
            _.each(quotes || {}, function (quote, code) {
                if (code !== data.carrier_code && (!best || quote.gross < best.gross)) {
                    best = {code: code, gross: quote.gross};
                }
            });
            this.cheaper(chosen && best && best.gross < chosen.gross - 0.005 ? {
                code: best.code,
                text: $t('%1 is %2 cheaper.')
                    .replace('%1', (carriers[best.code] || {}).name || best.code)
                    .replace('%2', (chosen.gross - best.gross).toFixed(2) + ' ' + currency)
            } : null);
        },

        /**
         * @returns {Object}
         */
        payload: function () {
            const data = this.source.get('data') || {},
                payload = _.pick(data, DRAFT_FIELDS);

            // Polkurier won't price a courier pickup without its date and window, and they don't
            // change the price: until they're chosen, price it as handed over at a point.
            if (payload.pickup_mode === 'courier'
                && !(rules.filled(payload.pickup_date) && rules.filled(payload.pickup_time_from) && rules.filled(payload.pickup_time_to))
            ) {
                payload.pickup_mode = 'drop_off';
                payload.pickup_date = payload.pickup_time_from = payload.pickup_time_to = '';
            }

            return payload;
        },

        quote: function () {
            const payload = this.payload(),
                serialized = JSON.stringify(payload),
                number = ++this.requestNumber;

            if (serialized === this.lastPayload) {
                return;
            }
            this.lastPayload = serialized;
            if (!this.canQuote(payload)) {
                // Polkurier can't price an incomplete draft; keep every carrier on offer meanwhile.
                ++this.requestNumber;
                this.source.set('quotes', null);
                this.message($t('Prices show once every parcel has its size and weight.'));
                this.quoting(false);
                this.source.set('quoting', false);
                this.refresh();

                return;
            }
            this.quoting(true);
            this.source.set('quoting', true);
            $.ajax({
                url: this.source.get('data.urls.calculate'),
                type: 'POST',
                dataType: 'json',
                data: {form_key: window.FORM_KEY, isAjax: true, data: payload}
            }).done(function (response) {
                if (number !== this.requestNumber) {
                    return;
                }
                const quotes = response.quotes && !_.isArray(response.quotes) ? response.quotes : {};

                // No quote at all means the draft couldn't be priced, not that no carrier takes it.
                this.source.set('quotes', _.isEmpty(quotes) ? null : quotes);
                this.message(response.error ? response.message : '');
            }.bind(this)).fail(function () {
                if (number === this.requestNumber) {
                    this.message($t('Prices could not be loaded.'));
                    this.lastPayload = null;
                }
            }.bind(this)).always(function () {
                if (number === this.requestNumber) {
                    this.quoting(false);
                    this.source.set('quoting', false);
                    this.refresh();
                }
            }.bind(this));
        },

        /**
         * @param {Object} payload
         * @returns {Boolean}
         */
        canQuote: function (payload) {
            const packs = (payload.packs || []).filter(function (pack) {
                return pack && !pack.delete;
            });

            return rules.filled(payload.carrier_code) && packs.length > 0 && packs.every(function (pack) {
                return rules.amount(pack.weight) > 0 && rules.amount(pack.length) > 0
                    && rules.amount(pack.width) > 0 && rules.amount(pack.height) > 0;
            });
        },

        /**
         * @param {Object} issue
         */
        fix: function (issue) {
            if (MODALS[issue.card]) {
                registry.get(this.formName + '.' + MODALS[issue.card], function (modal) {
                    modal.openModal();
                });
            } else {
                const target = $(issue.card === 'parcels' ? '.polkurier-workspace__parcels' : '.polkurier-workspace__carriers');

                if (target.length) {
                    target[0].scrollIntoView({behavior: 'smooth', block: 'center'});
                }
            }
        },

        useCheaper: function () {
            const cheaper = this.cheaper();

            if (cheaper) {
                this.source.set('data.carrier_code', cheaper.code);
            }
        },

        place: function () {
            registry.get(this.formName, function (form) {
                form.save();
            });
        },

        /**
         * Brings the first invalid field into view: its modal, if it's in one.
         *
         * @param {Boolean} invalid
         */
        onInvalid: function (invalid) {
            if (!invalid) {
                return;
            }
            _.defer(function () {
                const modal = _.find(_.values(MODALS), function (name) {
                    const component = registry.get(this.formName + '.' + name);

                    return component && this.hasError(component);
                }, this);

                if (modal) {
                    registry.get(this.formName + '.' + modal).openModal();

                    return;
                }
                const error = $('.polkurier-workspace .admin__field-error:visible').first();

                if (error.length) {
                    error[0].scrollIntoView({behavior: 'smooth', block: 'center'});
                }
            }.bind(this));
        },

        /**
         * @param {Object} component
         * @returns {Boolean}
         */
        hasError: function (component) {
            if (typeof component.error === 'function' && component.error()) {
                return true;
            }

            return typeof component.elems === 'function' && component.elems().some(this.hasError, this);
        }
    });
});
