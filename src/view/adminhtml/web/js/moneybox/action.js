/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * A button of the payout form that asks Polkurier something: the fee, or a confirmation code.
 * A code request stores the token that the payout must carry.
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
            action: 'quote',
            buttonLabel: ''
        },

        initialize: function () {
            this._super();
            this.message = ko.observable('');
            this.failed = ko.observable(false);
            this.loading = ko.observable(false);

            return this;
        },

        run: function () {
            this.loading(true);
            $.ajax({
                url: this.source.get('data.urls.' + this.action),
                type: 'POST',
                dataType: 'json',
                data: {form_key: window.FORM_KEY, data: this.source.get('data')}
            }).done(function (response) {
                this.failed(!!response.error);
                this.message(response.message);
                if (!response.error && response.token) {
                    this.source.set('data.two_factor_token', response.token);
                }
            }.bind(this)).fail(function () {
                this.failed(true);
                this.message($t('Polkurier could not be asked.'));
            }.bind(this)).always(function () {
                this.loading(false);
            }.bind(this));
        }
    });
});
