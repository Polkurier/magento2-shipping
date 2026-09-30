/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Posts to the module's admin actions, with the form key and isAjax, so an expired session
 * answers in a way Magento's admin scripts redirect on instead of with the login page.
 *
 * Each sender counts its own posts and hands on only the answer to the latest: a sender used for
 * one kind of request (the prices, the collection dates) never lets an older answer overwrite a
 * newer one. Every handler is optional.
 */
define(['jquery'], function ($) {
    'use strict';

    /**
     * @returns {{post: Function, cancel: Function}}
     */
    return function () {
        let latest = 0;

        return {
            /**
             * @param {String} url
             * @param {Object} data sent along with the form key and isAjax
             * @param {{done: Function, fail: Function, always: Function}} handlers
             */
            post: function (url, data, handlers) {
                const number = ++latest,
                    call = function (name, argument) {
                        if (number === latest && handlers && handlers[name]) {
                            handlers[name](argument);
                        }
                    };

                $.ajax({
                    url: url,
                    type: 'POST',
                    dataType: 'json',
                    data: $.extend({form_key: window.FORM_KEY, isAjax: true}, data)
                }).done(function (response) {
                    call('done', response);
                }).fail(function () {
                    call('fail');
                }).always(function () {
                    call('always');
                });
            },

            /**
             * Forgets the answers still on their way.
             */
            cancel: function () {
                latest++;
            }
        };
    };
});
