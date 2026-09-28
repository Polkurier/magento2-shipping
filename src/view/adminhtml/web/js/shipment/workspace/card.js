/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * A condensed summary of one group of shipment options, with its issues. The options themselves
 * are edited in the group's modal.
 */
define([
    'uiComponent',
    'uiRegistry',
    'ko',
    'underscore',
    'mage/translate',
    './rules'
], function (Component, registry, ko, _, $t, rules) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/workspace/card',
            provider: '${ $.ns }.${ $.ns }_data_source',
            kind: '',
            title: '',
            modal: '',
            pointField: '${ $.ns }.${ $.ns }.recipient_modal.recipient.recipient_point_id'
        },

        initialize: function () {
            this._super();
            this.lines = ko.observableArray([]);
            this.issues = ko.observableArray([]);
            this.shown = ko.observable(true);
            this.canChoosePoint = ko.observable(false);
            this.attention = ko.pureComputed(function () {
                return this.issues().some(function (issue) {
                    return issue.blocking;
                });
            }, this);
            this.source.on('data', _.debounce(this.refresh.bind(this), 30));
            this.source.on('quotes', _.debounce(this.refresh.bind(this), 30));
            this.refresh();

            return this;
        },

        refresh: function () {
            const data = this.source.get('data') || {},
                features = data.features || {},
                builder = this['lines_' + this.kind];

            this.lines((builder ? builder.call(this, data, features) : []).map(function (line) {
                return {text: line.text, muted: !!line.muted, strong: !!line.strong, tag: !!line.tag};
            }));
            this.issues(rules.issues(data, this.source.get('quotes') || null).filter(function (issue) {
                return issue.card === this.kind;
            }, this));
            this.shown(this.isShown(data, features));
            this.canChoosePoint(this.kind === 'recipient' && features.point_allowed !== false && !!(data.map || {}).token);
        },

        /**
         * @param {Object} data
         * @param {Object} features
         * @returns {Boolean}
         */
        isShown: function (data, features) {
            switch (this.kind) {
                case 'cod':
                    return features.cod !== false || rules.amount(data.cod_amount) > 0;
                case 'fields':
                    return !!features.has_fields;
                default:
                    return true;
            }
        },

        edit: function () {
            registry.get(this.modal, function (modal) {
                modal.openModal();
            });
        },

        chooseOnMap: function () {
            registry.get(this.pointField, function (field) {
                field.chooseOnMap();
            });
        },

        /**
         * @param {String} field
         * @param {*} value
         * @returns {String}
         */
        choice: function (field, value) {
            const labels = (this.source.get('data.choices') || {})[field] || {};

            return labels[value === null || value === undefined ? '' : String(value)] || '';
        },

        /**
         * @param {*} value
         * @returns {String}
         */
        money: function (value) {
            return rules.amount(value).toFixed(2) + ' ' + (this.source.get('data.currency') || 'PLN');
        },

        lines_recipient: function (data, features) {
            const lines = [],
                street = [data.recipient_street, [data.recipient_house_number, data.recipient_flat_number].filter(rules.filled).join('/')]
                    .filter(rules.filled).join(' '),
                city = [data.recipient_postcode, data.recipient_city].filter(rules.filled).join(' ');

            lines.push({text: [data.recipient_person, data.recipient_company].filter(rules.filled).join(', ')});
            lines.push({text: street});
            lines.push({text: city + (data.recipient_country && data.recipient_country !== 'PL' ? ', ' + data.recipient_country : '')});
            lines.push({text: [data.recipient_phone, data.recipient_email].filter(rules.filled).join(' · '), muted: true});
            if (features.point_allowed !== false) {
                lines.push(rules.filled(data.recipient_point_id)
                    ? {text: $t('Pickup point: %1').replace('%1', data.recipient_point_id), strong: true}
                    : {text: features.point_required ? $t('No pickup point chosen') : $t('To the door (no pickup point)'), muted: !features.point_required});
            }

            return lines.filter(function (line) {
                return rules.filled(line.text);
            });
        },

        lines_shipment: function (data) {
            return [
                {text: this.choice('shipment_type', data.shipment_type), strong: true},
                {text: $t('From: %1').replace('%1', this.choice('sender_address_id', data.sender_address_id) || $t('the shipping origin'))},
                {text: $t('Description: %1').replace('%1', data.description || '—'), muted: true}
            ];
        },

        lines_handover: function (data, features) {
            const lines = [{text: this.choice('pickup_mode', data.pickup_mode), strong: true}];

            if (data.pickup_mode === 'courier' && features.pickup_date !== false) {
                if (rules.filled(data.pickup_date)) {
                    lines.push({text: data.pickup_date + (rules.filled(data.pickup_time_from) && rules.filled(data.pickup_time_to)
                        ? ', ' + data.pickup_time_from + '–' + data.pickup_time_to
                        : '')});
                } else {
                    lines.push({text: $t('No collection date chosen'), muted: !features.pickup_date_required});
                }
            }

            return lines;
        },

        lines_cod: function (data) {
            if (rules.amount(data.cod_amount) <= 0) {
                return [{text: $t('None'), muted: true}];
            }

            return [
                {text: this.money(data.cod_amount), strong: true},
                {text: [this.choice('cod_type', data.cod_type), this.choice('cod_return_type', data.cod_return_type)].filter(rules.filled).join(' · ')},
                {text: data.cod_return_type === 'BA' ? this.choice('cod_bank_account_id', data.cod_bank_account_id) : '', muted: true}
            ].filter(function (line) {
                return rules.filled(line.text);
            });
        },

        lines_extras: function (data, features) {
            const lines = [];

            if (features.insurance !== false) {
                lines.push(rules.amount(data.insurance_amount) > 0
                    ? {text: $t('Insured for %1').replace('%1', this.money(data.insurance_amount)), strong: true}
                    : {text: $t('Not insured'), muted: true});
            }
            (data.services || []).forEach(function (service) {
                lines.push({text: this.choice('services', service) || service, tag: true});
            }, this);
            if (!(data.services || []).length) {
                lines.push({text: $t('No additional services'), muted: true});
            }
            if (rules.filled(data.cover_address_id)) {
                lines.push({text: $t('Cover address: %1').replace('%1', this.choice('cover_address_id', data.cover_address_id)), muted: true});
            }

            return lines;
        },

        lines_fields: function (data, features) {
            const values = data.additional_fields || {};

            return (features.fields || []).map(function (field) {
                return rules.filled(values[field.name])
                    ? {text: field.label + ': ' + values[field.name]}
                    : {text: field.label + ': —', muted: true};
            });
        }
    });
});
