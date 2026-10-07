/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Carrier-specific fields, as the chosen carrier declares them. Their values go to
 * data.additional_fields, keyed by field name. The SDK does not carry select options for these
 * fields, so each is a text input.
 */
define(['uiComponent', 'ko'], function (Component, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            fields: [],
            imports: {
                fields: '${ $.provider }:data.features.fields'
            },
            listens: {
                fields: 'rebuild'
            }
        },

        initialize: function () {
            this._super();
            this.inputs = ko.observableArray([]);
            this.rebuild(this.fields);

            return this;
        },

        /**
         * @param {Array} fields
         */
        rebuild: function (fields) {
            const values = this.source.get('data.additional_fields') || {};

            if (!this.inputs) {
                return;
            }
            this.inputs((fields || []).map(function (field) {
                const value = ko.observable(values[field.name] || '');

                value.subscribe(function (newValue) {
                    this.source.set('data.additional_fields.' + field.name, newValue);
                }, this);

                return {
                    name: field.name,
                    label: field.label,
                    description: field.description,
                    required: field.required,
                    value: value
                };
            }, this));
        }
    });
});
