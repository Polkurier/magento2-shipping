/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * The shipment screen's data, saved as the draft alone (rules.buildDraft). The carriers, choices and
 * templates the screen also shows would push the request past PHP's limit on the number of input
 * values, and the server would never see it.
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
            this.client.save(rules.buildDraft(this.get('data') || {}, true), options);

            return this;
        }
    });
});
