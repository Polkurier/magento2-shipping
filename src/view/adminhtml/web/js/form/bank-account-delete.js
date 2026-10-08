/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Deletes the bank account open in the form, once confirmed. Where the account locks bank accounts
 * with a security PIN, a window asks for the PIN; otherwise a plain confirmation is enough.
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
     * @param {String} sourceName the form's data source, which says whether a PIN is needed
     */
    return function (message, url, id, sourceName) {
        const source = registry.get(sourceName),
            post = function (pin) {
                $.mage.dataPost().postData({
                    action: url,
                    data: {id: id, confirmation_pin: pin || '', form_key: window.FORM_KEY}
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
            content: $('<div/>')
                .text(message + ' ' + $t('Your Polkurier account locks bank accounts with a security PIN: enter it.'))
                .html(),
            attributesField: {type: 'password', autocomplete: 'off', 'aria-label': $t('Security PIN')},
            actions: {confirm: function (pin) {
                post(pin);
            }}
        });
    };
});
