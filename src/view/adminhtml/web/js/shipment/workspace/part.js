/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * What the parts of the shipment screen share: they read the form's data and redraw when it or
 * the prices change, and open the form's modals.
 */
define([
    'uiComponent',
    'uiRegistry',
    'underscore',
    './rules'
], function (Component, registry, _, rules) {
    'use strict';

    return Component.extend({
        defaults: {
            provider: '${ $.ns }.${ $.ns }_data_source',
            formName: '${ $.ns }.${ $.ns }'
        },

        initialize: function () {
            this._super();
            this.initState();
            // One redraw however many of these change together.
            const refresh = _.debounce(this.refresh.bind(this), 30);

            ['data', 'quotes', 'quoting', 'validated'].forEach(function (event) {
                this.source.on(event, refresh, this.name);
            }, this);
            this.refresh();

            return this;
        },

        /**
         * Removes the part's listeners from the form's data, and from the modals it opened, as it goes.
         */
        destroy: function () {
            this.source.off(this.name);
            _.keys(this.watchedModals || {}).forEach(function (name) {
                const modal = registry.get(this.formName + '.' + name);

                if (modal) {
                    modal.off(this.name);
                }
            }, this);
            this._super();
        },

        /**
         * Creates the part's observables; called before the first refresh.
         */
        initState: function () {
        },

        /**
         * Redraws the part from the form's data.
         */
        refresh: function () {
        },

        /**
         * Opens whatever fixes a problem that the part's section reports.
         *
         * @param {Object} issue
         */
        fix: function (issue) {
        },

        /**
         * @param {String} [path] below "data"
         * @returns {*}
         */
        get: function (path) {
            return this.source.get(path ? 'data.' + path : 'data');
        },

        /**
         * @param {String} path below "data"
         * @param {*} value
         */
        set: function (path, value) {
            this.source.set('data.' + path, value);
        },

        /**
         * @returns {Boolean} whether placing the shipment was tried, so what's missing is marked
         */
        validated: function () {
            return Boolean(this.source.get('validated'));
        },

        /**
         * @returns {Object}
         */
        features: function () {
            return this.get('features') || {};
        },

        /**
         * @param {*} value
         * @returns {String}
         */
        money: function (value) {
            return rules.money(value, this.get('currency'));
        },

        /**
         * @param {String} field
         * @param {*} value
         * @returns {String}
         */
        choice: function (field, value) {
            const labels = (this.get('choices') || {})[field] || {};

            return labels[value === null || value === undefined ? '' : String(value)] || '';
        },

        /**
         * @param {String} name the modal's name in the form
         */
        openModal: function (name) {
            this.watchedModals = this.watchedModals || {};
            registry.get(this.formName + '.' + name, function (modal) {
                // A modal closed without saving changes nothing, so nothing would redraw the part,
                // and a switch flipped to open it would stay on.
                if (!this.watchedModals[name]) {
                    this.watchedModals[name] = true;
                    modal.on('state', function (open) {
                        if (!open) {
                            this.refresh();
                        }
                    }.bind(this), this.name);
                }
                modal.openModal();
            }.bind(this));
        }
    });
});
