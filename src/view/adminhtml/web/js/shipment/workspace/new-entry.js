/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * A sender or bank account typed in for the shipment rather than chosen from those stored: its
 * fields, shown while "new" is chosen, and whether it's saved for later shipments, under a name.
 * The fields are the form's data, as the draft has them (new_sender_*, new_bank_account_*).
 */
define([
    'ko',
    'underscore',
    './part',
    './rules'
], function (ko, _, Part, rules) {
    'use strict';

    return Part.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/workspace/new-entry',
            // The choice whose "new" shows the fields.
            choiceField: '',
            // Also shown only while these fields have the given values, e.g. paid out to a bank
            // account.
            alsoWhen: {},
            fields: [],
            keepField: '',
            nameField: '',
            keepLabel: '',
            nameLabel: '',
            nameNotice: '',
            intro: ''
        },

        initState: function () {
            this.shown = ko.observable(false);
            this.rows = _.map(this.fields, function (definition) {
                const row = _.extend({type: 'text', required: false, maxlength: null}, definition, {
                    id: 'polkurier-' + definition.name,
                    value: ko.observable('')
                });

                row.value.subscribe(function (value) {
                    if (String(this.get(row.name) || '') !== String(value || '')) {
                        this.set(row.name, value);
                    }
                }, this);
                row.missing = ko.pureComputed(function () {
                    return row.required && this.validated() && !rules.filled(row.value());
                }, this);

                return row;
            }, this);
            this.keep = ko.observable(false);
            this.keep.subscribe(function (keep) {
                if (Boolean(this.get(this.keepField)) !== keep) {
                    this.set(this.keepField, keep);
                }
            }, this);
            this.keptName = ko.observable('');
            this.keptName.subscribe(function (name) {
                if (String(this.get(this.nameField) || '') !== name) {
                    this.set(this.nameField, name);
                }
            }, this);
            this.ids = {keep: 'polkurier-' + this.keepField, name: 'polkurier-' + this.nameField};
        },

        refresh: function () {
            const data = this.get() || {},
                shown = data[this.choiceField] === 'new' && _.every(this.alsoWhen, function (value, field) {
                    return data[field] === value;
                });

            this.shown(shown);
            this.rows.forEach(function (row) {
                const value = data[row.name] === undefined || data[row.name] === null ? '' : String(data[row.name]);

                if (row.value() !== value) {
                    row.value(value);
                }
            });
            this.keep(Boolean(data[this.keepField]) && data[this.keepField] !== '0');
            this.keptName(
                data[this.nameField] === undefined || data[this.nameField] === null ? '' : String(data[this.nameField])
            );
        }
    });
});
