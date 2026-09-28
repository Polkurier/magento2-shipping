/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Collection date and window: only asked for when a courier collects and the carrier takes a date.
 */
define(['Magento_Ui/js/form/element/abstract'], function (Abstract) {
    'use strict';

    return Abstract.extend({
        defaults: {
            mode: 'courier',
            dateSupported: true,
            imports: {
                mode: '${ $.provider }:data.pickup_mode',
                dateSupported: '${ $.provider }:data.features.pickup_date'
            },
            listens: {
                mode: 'updateVisibility',
                dateSupported: 'updateVisibility'
            }
        },

        initialize: function () {
            this._super();
            this.updateVisibility();

            return this;
        },

        updateVisibility: function () {
            this.visible(this.mode === 'courier' && this.dateSupported !== false);
        }
    });
});
