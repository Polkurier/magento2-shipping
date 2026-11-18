/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * The payout's fee, shown atop the payout form with what the money box has to pay out. Polkurier
 * is asked again a moment after the last change to what the fee depends on (the orders paid out,
 * and how).
 */
define([
    'uiComponent',
    'ko',
    'underscore',
    'mage/translate',
    'Polkurier_Shipping/js/request',
    'Polkurier_Shipping/js/shipment/workspace/rules'
], function (Component, ko, _, $t, request, rules) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Polkurier_Shipping/payout/fee',
            provider: '${ $.ns }.${ $.ns }_data_source',
            // Below "data": what the fee depends on.
            fields: ['orders', 'payout_type']
        },

        initialize: function () {
            this._super();
            this.loading = ko.observable(false);
            this.error = ko.observable('');
            this.fee = ko.observable(null);
            // Numbers typed in that this shop doesn't know to be in the money box.
            this.unknown = ko.observable('');
            this.requests = request();
            const refresh = _.debounce(this.refresh.bind(this), 600);

            this.fields.forEach(function (field) {
                this.source.on('data.' + field, refresh);
            }, this);
            this.refresh();

            return this;
        },

        refresh: function () {
            const priceFormat = this.source.get('data.price_format');

            this.loading(true);
            this.requests.post(this.source.get('data.urls.quote'), {
                data: _.pick(this.source.get('data') || {}, this.fields)
            }, {
                done: function (response) {
                    this.error(response.error ? response.message : '');
                    this.unknown(!response.error && (response.unknown || []).length
                        ? $t('Not in the money box as far as this shop knows: %1. Polkurier leaves out the orders it can\'t pay out, without saying so.')
                            .replace('%1', response.unknown.join(', '))
                        : '');
                    this.fee(response.error ? null : {
                        gross: rules.formatMoney(response.fee, priceFormat),
                        net: rules.formatMoney(response.fee_net, priceFormat),
                        free: $t('Free payouts left: %1 of %2.')
                            .replace('%1', response.free_left)
                            .replace('%2', response.free_total),
                        available: typeof response.available === 'number'
                            ? $t('Available to pay out: %1.').replace('%1', rules.formatMoney(response.available, priceFormat))
                            : ''
                    });
                }.bind(this),
                fail: function () {
                    this.error($t('Polkurier could not be asked.'));
                    this.fee(null);
                }.bind(this),
                always: function () {
                    this.loading(false);
                }.bind(this)
            });
        }
    });
});
