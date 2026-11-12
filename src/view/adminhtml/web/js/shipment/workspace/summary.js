/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * The foot of the side column: the price with the chosen carrier, a cheaper carrier if there is
 * one, and whether the customer is e-mailed about the Magento shipment once the Polkurier order is
 * placed (offered only when nobody was e-mailed about it yet). The shipment is placed with the
 * page's button. If something is missing, placing marks
 * it in the sections instead of sending an order Polkurier would refuse.
 *
 * Whenever something that affects the price changes, it asks Polkurier again, once things settle,
 * and publishes every carrier's price as the provider's "quotes", for the carrier list and the
 * shipment section. When the form turns out invalid, it opens the modal holding the first invalid
 * field, or brings the first section with something missing into view.
 */
define([
    'uiComponent',
    'uiRegistry',
    'jquery',
    'ko',
    'underscore',
    'mage/translate',
    './rules',
    'Polkurier_Shipping/js/request'
], function (Component, registry, $, ko, _, $t, rules, request) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/workspace/summary',
            provider: '${ $.ns }.${ $.ns }_data_source',
            formName: '${ $.ns }.${ $.ns }',
            quoteDelay: 600,
            // The form's modals, from its XML: an invalid field in one of them opens it.
            modals: [],
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
            this.failed = ko.observable(false);
            this.retryable = ko.observable(false);
            this.carrierName = ko.observable('');
            this.cheaper = ko.observable(null);
            this.notifyOffered = ko.observable(false);
            this.notifyCustomer = ko.observable(false);
            this.notifyCustomer.subscribe(function (notify) {
                if (notify !== Boolean(this.source.get('data.notify_customer'))) {
                    this.source.set('data.notify_customer', notify);
                }
            }, this);
            this.lastPayload = null;
            // Only the answer to the latest price request counts.
            this.prices = request();
            this.scheduleQuote = _.debounce(this.quote.bind(this), this.quoteDelay);
            this.source.on('data', _.debounce(this.onChange.bind(this), 30), this.name);
            this.source.on('quotes', _.debounce(this.refresh.bind(this), 30), this.name);
            this.onChange();
            this.source.on('data.validate', this.onValidate.bind(this), this.name);

            return this;
        },

        /**
         * Removes the summary's listeners from the form's data as it goes.
         */
        destroy: function () {
            this.source.off(this.name);
            this._super();
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
            this.notifyOffered(Boolean(data.notify_offered));
            this.notifyCustomer(Boolean(data.notify_customer));
            this.publishIssues();
            this.price(chosen ? {
                gross: rules.formatMoney(chosen.gross, currency),
                net: rules.formatMoney(chosen.net, currency)
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
                    .replace('%2', rules.formatMoney(chosen.gross - best.gross, currency))
            } : null);
        },

        /**
         * @returns {Object}
         */
        buildPayload: function () {
            const data = this.source.get('data') || {},
                payload = rules.buildDraft(data);

            // Polkurier won't price a courier pickup without its date and window, and they don't
            // change the price: until they're chosen, price it as handed over at a point.
            if (payload.pickup_mode === 'courier'
                && !(rules.isFilled(payload.pickup_date)
                    && rules.isFilled(payload.pickup_time_from)
                    && rules.isFilled(payload.pickup_time_to))
            ) {
                payload.pickup_mode = 'drop_off';
                payload.pickup_date = payload.pickup_time_from = payload.pickup_time_to = '';
            }

            return payload;
        },

        quote: function () {
            const payload = this.buildPayload(),
                serialized = JSON.stringify(payload);

            // A change outside the priced fields must not cancel the request already on its way.
            if (serialized === this.lastPayload) {
                return;
            }
            this.lastPayload = serialized;

            if (!this.canQuote(payload)) {
                // Polkurier can't price an incomplete draft; keep every carrier on offer meanwhile.
                this.prices.cancel();
                this.source.set('quotes', null);
                this.setMessage($t('Prices show once every parcel has its size and weight.'));
                this.quoting(false);
                this.source.set('quoting', false);
                this.refresh();

                return;
            }
            this.quoting(true);
            this.source.set('quoting', true);
            this.prices.post(this.source.get('data.urls.calculate'), {data: payload}, {
                done: function (response) {
                    const quotes = response.quotes && !_.isArray(response.quotes) ? response.quotes : {};

                    // No quote at all means the draft couldn't be priced, not that no carrier takes it.
                    this.source.set('quotes', _.isEmpty(quotes) ? null : quotes);
                    // Asked again when the merchant says so, or when the draft changes.
                    this.setMessage(
                        response.error ? response.message : '',
                        Boolean(response.error),
                        Boolean(response.retry)
                    );
                }.bind(this),
                fail: function () {
                    this.setMessage($t('Prices could not be loaded.'), true, true);
                    this.lastPayload = null;
                }.bind(this),
                always: function () {
                    this.quoting(false);
                    this.source.set('quoting', false);
                    this.refresh();
                }.bind(this)
            });
        },

        /**
         * @param {String} message why there's no price, shown in the shipment section too
         * @param {Boolean} [failed] whether pricing failed
         * @param {Boolean} [retryable] whether asking again may help: not after a refusal, which would repeat
         */
        setMessage: function (message, failed, retryable) {
            this.message(message);
            this.failed(Boolean(failed));
            this.retryable(Boolean(failed && retryable));
            this.source.set('quoteMessage', message);
        },

        retry: function () {
            this.lastPayload = null;
            this.quote();
        },

        /**
         * @param {Object} payload
         * @returns {Boolean}
         */
        canQuote: function (payload) {
            const packs = (payload.packs || []).filter(function (pack) {
                return pack && !pack.delete;
            });

            return rules.isFilled(payload.carrier_code) && packs.length > 0 && packs.every(function (pack) {
                return rules.parseAmount(pack.weight) > 0 && rules.parseAmount(pack.length) > 0
                    && rules.parseAmount(pack.width) > 0 && rules.parseAmount(pack.height) > 0;
            });
        },

        /**
         * Works out what needs attention, once for the whole screen, and publishes it as the
         * provider's "issues" for the sections.
         *
         * @returns {Array<{card: String, text: String, blocking: Boolean, fix: (Object|Boolean|null)}>}
         */
        publishIssues: function () {
            const issues = rules.findIssues(this.source.get('data') || {});

            this.source.set('issues', issues);

            return issues;
        },

        useCheaper: function () {
            const cheaper = this.cheaper();

            if (cheaper) {
                this.source.set('data.carrier_code', cheaper.code);
            }
        },

        /**
         * Placing with something missing marks it in its section and brings the first one into
         * view, instead of sending an order Polkurier would refuse: the form validates before it
         * saves, and a missing piece makes it invalid as an invalid field does.
         */
        onValidate: function () {
            this.source.set('validated', true);
            if (this.publishIssues().some(function (issue) {
                return issue.blocking;
            })) {
                this.source.set('params.invalid', true);
            }
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
                const modal = _.find(this.modals, function (name) {
                    const component = registry.get(this.formName + '.' + name);

                    return component && this.hasError(component);
                }, this);

                if (modal) {
                    registry.get(this.formName + '.' + modal).openModal();

                    return;
                }
                const error = $('.polkurier-workspace .admin__field-error:visible').first(),
                    section = $('.polkurier-section._invalid').first();

                if (error.length) {
                    error[0].scrollIntoView({behavior: 'smooth', block: 'center'});
                } else if (section.length) {
                    section[0].scrollIntoView({behavior: 'smooth', block: 'start'});
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
