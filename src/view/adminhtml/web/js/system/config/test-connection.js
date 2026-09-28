/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Sends the customer id and token as typed (a masked token means "the saved one") and shows
 * whether Polkurier accepts them.
 */
define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    return function (config, element) {
        const $element = $(element),
            $result = $element.find('.polkurier-test-connection__result');

        $element.find('.polkurier-test-connection').on('click', function () {
            $result.text($t('Checking…')).css('color', '');
            $.ajax({
                url: config.url,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: window.FORM_KEY,
                    customer_id: $('#carriers_polkurier_general_customer_id').val(),
                    api_token: $('#carriers_polkurier_general_api_token').val()
                }
            }).done(function (response) {
                $result.text(response.message).css('color', response.success ? '#006400' : '#e22626');
            }).fail(function () {
                $result.text($t('The check could not be run.')).css('color', '#e22626');
            });
        });
    };
});
