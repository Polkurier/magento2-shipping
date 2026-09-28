/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Additional services, narrowed to those the chosen carrier sells.
 */
define([
    'Magento_Ui/js/form/element/checkbox-set',
    'ko'
], function (CheckboxSet, ko) {
    'use strict';

    return CheckboxSet.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/services',
            allowed: null,
            imports: {
                allowed: '${ $.provider }:data.features.services'
            },
            listens: {
                allowed: 'narrow'
            }
        },

        initialize: function () {
            this._super();
            this.visibleOptions = ko.observableArray(this.options);
            this.narrow(this.allowed);

            return this;
        },

        /**
         * @param {String[]|null} allowed null when the carrier is unknown: everything is offered
         */
        narrow: function (allowed) {
            if (!this.visibleOptions) {
                return;
            }
            this.visibleOptions(allowed === null ? this.options : this.options.filter(function (option) {
                return allowed.indexOf(option.value) !== -1;
            }));
            this.value((this.value() || []).filter(function (value) {
                return allowed === null || allowed.indexOf(value) !== -1;
            }));
        }
    });
});
