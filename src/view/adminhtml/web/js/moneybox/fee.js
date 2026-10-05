/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The payout's fee, atop the payout form, with what the money box has to pay out: asked of
 * Polkurier again whenever what it depends on changes (the orders paid out and how), a moment
 * after the last change.
 */
define([
    'uiComponent',
    'ko',
    'underscore',
    'mage/translate',
    'Polkurier_Shipping/js/request'
], function (Component, ko, _, $t, request) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Polkurier_Shipping/moneybox/fee',
            provider: '${ $.ns }.${ $.ns }_data_source',
            // Below "data": what the fee depends on.
            fields: ['orders', 'payout_type']
        },

        initialize: function () {
            this._super();
            this.loading = ko.observable(false);
            this.error = ko.observable('');
            this.fee = ko.observable(null);
            // Numbers typed in that aren't known here as in the money box.
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
                        gross: Number(response.fee).toFixed(2),
                        net: Number(response.fee_net).toFixed(2),
                        free: $t('Free payouts left: %1 of %2.')
                            .replace('%1', response.free_left)
                            .replace('%2', response.free_total),
                        available: typeof response.available === 'number'
                            ? $t('Available to pay out: %1 PLN.').replace('%1', Number(response.available).toFixed(2))
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
