/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * The shipment details page. Everything on it is prepared by the data provider; this only lays
 * the sections and tables out.
 *
 * The page first shows what is stored. When the data provider says there is something to read
 * from Polkurier ("refresh"), it is read in the background and the sections built from it take the
 * place of the stored ones; when it can't be read, the stored ones stay, with the errors. A state
 * that changed meanwhile reloads the page, whose buttons follow the state.
 */
define([
    'jquery',
    'ko',
    'mage/translate',
    'uiComponent',
    'Polkurier_Shipping/js/request'
], function ($, ko, $t, Component, request) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/view',
            provider: '${ $.ns }.${ $.ns }_data_source'
        },

        initialize: function () {
            this._super();
            this.view = {
                errors: ko.observableArray(),
                notes: ko.observableArray(),
                sections: ko.observableArray(),
                tables: ko.observableArray(),
                synced: ko.observable('')
            };
            this.updating = ko.observable(false);
            this.show(this.source.get('data.view') || {});
            if (this.source.get('data.refresh')) {
                this.refresh();
            }

            return this;
        },

        /**
         * @param {Object} view the sections and lines built by the data provider or the refresh
         */
        show: function (view) {
            ['errors', 'notes', 'sections', 'tables'].forEach(function (key) {
                this.view[key](view[key] || []);
            }, this);
            this.view.synced(view.synced || '');
        },

        /**
         * Puts when the page was read from Polkurier, and the update under way, on the left of the
         * page's buttons bar.
         *
         * @param {HTMLElement} element
         */
        placeSync: function (element) {
            $('.page-main-actions').first().prepend(element);
        },

        refresh: function () {
            this.updating(true);
            request().post(this.source.get('data.urls.refresh'), {}, {
                done: function (response) {
                    if (response.view && response.state && response.state !== this.source.get('data.state')) {
                        window.location.reload();
                    } else if (response.view) {
                        this.show(response.view);
                    } else {
                        this.view.errors(response.errors || [response.message]);
                    }
                }.bind(this),
                fail: function () {
                    this.view.errors([$t('Polkurier could not be asked.')]);
                }.bind(this),
                always: function () {
                    this.updating(false);
                }.bind(this)
            });
        }
    });
});
