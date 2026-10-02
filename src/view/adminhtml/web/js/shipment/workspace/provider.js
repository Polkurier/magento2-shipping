/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The shipment screen's data, placed as the draft alone (rules.draft): the carriers, choices and
 * templates the screen shows besides would take the request past PHP's limit on how many values
 * one may carry, and the server would never see it.
 */
define([
    'Magento_Ui/js/form/provider',
    './rules'
], function (Provider, rules) {
    'use strict';

    return Provider.extend({
        /**
         * @param {Object} [options]
         * @returns {Object}
         */
        save: function (options) {
            this.client.save(rules.draft(this.get('data') || {}, true), options);

            return this;
        }
    });
});
