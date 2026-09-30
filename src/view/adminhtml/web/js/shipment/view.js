/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The shipment details page. Everything on it is prepared by the data provider; this only lays
 * the sections and tables out.
 */
define(['uiComponent'], function (Component) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/view',
            provider: '${ $.ns }.${ $.ns }_data_source'
        },

        initialize: function () {
            this._super();
            this.view = this.source.get('data.view') || {sections: [], tables: [], notes: [], errors: []};

            return this;
        }
    });
});
