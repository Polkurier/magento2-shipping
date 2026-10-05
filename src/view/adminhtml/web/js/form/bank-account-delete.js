/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Deletes the bank account open in the form, once confirmed: with the security PIN where the
 * account locks bank accounts with one, which a window asks for, otherwise by confirming.
 */
define([
    'jquery',
    'mage/translate',
    'uiRegistry',
    'Magento_Ui/js/modal/confirm',
    'Magento_Ui/js/modal/prompt',
    'mage/dataPost'
], function ($, $t, registry, confirm, prompt) {
    'use strict';

    /**
     * @param {String} message
     * @param {String} url
     * @param {Number} id
     */
    return function (message, url, id) {
        const source = registry.get('polkurier_bank_account_form.polkurier_bank_account_form_data_source'),
            post = function (pin) {
                $.mage.dataPost().postData({
                    action: url,
                    data: {id: id, delete: 1, confirmation_pin: pin || '', form_key: window.FORM_KEY}
                });
            };

        if (!(source && source.get('data.pin_locked'))) {
            confirm({content: $('<div/>').text(message).html(), actions: {confirm: function () {
                post('');
            }}});

            return;
        }
        prompt({
            title: $t('Delete the bank account'),
            content: $('<div/>').text(message + ' ' + $t('Your Polkurier account locks bank accounts with a security PIN: enter it.')).html(),
            attributesField: {type: 'password', autocomplete: 'off', 'aria-label': $t('Security PIN')},
            actions: {confirm: function (pin) {
                post(pin);
            }}
        });
    };
});
