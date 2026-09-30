/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * A button of the payout form that asks Polkurier something: the fee, or a confirmation code.
 * A code request stores the token that the payout must carry. Only the fields the action needs
 * are sent: never the security PIN or a code typed in the form.
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
            action: 'quote',
            buttonLabel: '',
            fields: {}
        },

        initialize: function () {
            this._super();
            this.message = ko.observable('');
            this.failed = ko.observable(false);
            this.loading = ko.observable(false);
            this.requests = request();

            return this;
        },

        run: function () {
            this.loading(true);
            this.requests.post(this.source.get('data.urls.' + this.action), {
                data: _.pick(this.source.get('data'), _.uniq(_.values(this.fields)))
            }, {
                done: function (response) {
                    this.failed(!!response.error);
                    this.message(response.message);
                    if (!response.error && response.token) {
                        this.source.set('data.two_factor_token', response.token);
                    }
                }.bind(this),
                fail: function () {
                    this.failed(true);
                    this.message($t('Polkurier could not be asked.'));
                }.bind(this),
                always: function () {
                    this.loading(false);
                }.bind(this)
            });
        }
    });
});
