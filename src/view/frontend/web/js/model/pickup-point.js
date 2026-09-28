/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The pickup point chosen for each delivery method, shared by the checkout components.
 */
define(['ko'], function (ko) {
    'use strict';

    const config = window.checkoutConfig.pickupPoints || {},
        methods = config.methods || {},
        selected = {};

    Object.keys(methods).forEach(function (code) {
        selected[code] = ko.observable(null);
    });

    if (config.selected && selected[config.selected.method]) {
        selected[config.selected.method]({id: config.selected.id, label: config.selected.label});
    }

    return {
        config: config,

        /**
         * @param {Object} method a shipping rate
         * @returns {Boolean}
         */
        isOwnMethod: function (method) {
            return !!method && method.carrier_code === config.carrierCode;
        },

        /**
         * @param {String} methodCode
         * @returns {Boolean}
         */
        hasMap: function (methodCode) {
            return Object.prototype.hasOwnProperty.call(methods, methodCode);
        },

        /**
         * @param {String} methodCode
         * @returns {Boolean}
         */
        isRequired: function (methodCode) {
            return this.hasMap(methodCode) && !!methods[methodCode].required;
        },

        /**
         * @param {String} methodCode
         * @returns {String[]}
         */
        getProviders: function (methodCode) {
            return this.hasMap(methodCode) ? methods[methodCode].providers : [];
        },

        /**
         * @param {String} methodCode
         * @returns {{id: String, label: String}|null}
         */
        get: function (methodCode) {
            return selected[methodCode] ? selected[methodCode]() : null;
        },

        /**
         * @param {String} methodCode
         * @param {{id: String, label: String}} point
         */
        set: function (methodCode, point) {
            if (selected[methodCode]) {
                selected[methodCode](point);
            }
        }
    };
});
