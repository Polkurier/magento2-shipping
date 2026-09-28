/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Puts each carrier's logo in front of its method's label in the multi-address checkout.
 */
define(['jquery'], function ($) {
    'use strict';

    return function (config) {
        $('input.radio[name^="shipping_method["]').each(function () {
            const url = config.logos[this.value],
                label = url ? $('label[for="' + this.id + '"]') : $();

            if (label.length && !label.find('.shipping-method-logo').length) {
                label.prepend($('<img class="shipping-method-logo" alt="" loading="lazy"/>').attr('src', url));
            }
        });
    };
});
