/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Sends the customer id and token as typed (a masked token means "the saved one") and shows
 * whether Polkurier accepts them. The fields' ids come from the button's block.
 */
define(['jquery', 'mage/translate', 'Polkurier_Shipping/js/request'], function ($, $t, request) {
    'use strict';

    return function (config, element) {
        const $element = $(element),
            $result = $element.find('.polkurier-test-connection__result'),
            // A second click checks what's typed now; the answer to the first one is dropped.
            checks = request();

        /**
         * @param {String} text
         * @param {Boolean|null} success null while the check runs
         */
        function show(text, success) {
            $result.text(text).removeClass('message message-success message-error');
            if (success !== null) {
                $result.addClass('message ' + (success ? 'message-success' : 'message-error'));
            }
        }

        $element.find('.polkurier-test-connection').on('click', function () {
            show($t('Checking…'), null);
            checks.post(config.url, {
                customer_id: $('#' + config.fields.customer).val(),
                api_token: $('#' + config.fields.token).val()
            }, {
                done: function (response) {
                    show(response.message, !response.error);
                },
                fail: function () {
                    show($t('The check could not be run.'), false);
                }
            });
        });
    };
});
