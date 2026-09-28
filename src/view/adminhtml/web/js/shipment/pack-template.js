/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * A parcel's template: choosing one fills the parcel's type, dimensions and weight.
 */
define([
    'Magento_Ui/js/form/element/select',
    'uiRegistry'
], function (Select, registry) {
    'use strict';

    return Select.extend({
        defaults: {
            templates: {},
            listens: {
                value: 'applyTemplate'
            }
        },

        /**
         * @param {String} templateId
         */
        applyTemplate: function (templateId) {
            const template = templateId ? this.templates[templateId] : null;

            if (!template || !this.initialized) {
                return;
            }
            ['pack_type', 'length', 'width', 'height', 'weight'].forEach(function (field) {
                const element = registry.get(this.parentName + '.' + field);

                if (element) {
                    element.value(String(template[field]));
                }
            }, this);
        },

        initialize: function () {
            this._super();
            this.initialized = true;

            return this;
        }
    });
});
