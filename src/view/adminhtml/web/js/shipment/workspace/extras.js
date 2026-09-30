/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Cash on delivery, insurance and the carrier's additional services, each switched on and off in
 * place. Cash on delivery and insurance open their modals for the details.
 */
define([
    'ko',
    'underscore',
    'mage/translate',
    './part',
    './rules'
], function (ko, _, $t, Part, rules) {
    'use strict';

    return Part.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/workspace/extras',
            // The modals of the form that edit cash on delivery and insurance, from its XML.
            codModal: '',
            insuranceModal: ''
        },

        initState: function () {
            this.switches = ko.observableArray([]);
            this.coverOptions = ko.observableArray([]);
            this.coverAddressId = ko.observable('');
            this.coverAddressId.subscribe(function (value) {
                if ((this.get('cover_address_id') || '') !== (value || '')) {
                    this.set('cover_address_id', value || '');
                }
            }, this);
            this.lastServices = null;
        },

        refresh: function () {
            const data = this.get() || {},
                features = this.features(),
                allowed = features.services === undefined ? null : features.services,
                labels = (data.choices || {}).services || {},
                services = (data.services || []).slice(),
                switches = [];

            // A carrier change drops the services the new carrier doesn't sell.
            if (allowed !== null) {
                const kept = services.filter(function (service) {
                    return allowed.indexOf(service) !== -1;
                });

                if (kept.length !== services.length) {
                    this.set('services', kept);

                    return;
                }
            }

            if (features.cod !== false || rules.amount(data.cod_amount) > 0) {
                switches.push(this.codSwitch(data));
            }
            if (features.insurance !== false || rules.amount(data.insurance_amount) > 0) {
                switches.push(this.insuranceSwitch(data));
            }
            _.each(labels, function (label, service) {
                if (allowed === null || allowed.indexOf(service) !== -1) {
                    switches.push({
                        id: 'polkurier-service-' + service,
                        label: label,
                        checked: services.indexOf(service) !== -1,
                        toggle: this.toggleService.bind(this, service)
                    });
                }
            }, this);
            if ((data.cover_addresses || []).length) {
                switches.push({
                    id: 'polkurier-service-cover',
                    label: $t('Another sender on the label'),
                    checked: rules.filled(data.cover_address_id),
                    toggle: this.toggleCover.bind(this),
                    cover: true
                });
            }
            this.coverOptions(data.cover_addresses || []);
            this.coverAddressId(data.cover_address_id ? String(data.cover_address_id) : '');
            this.switches(switches);
        },

        /**
         * @param {Object} data
         * @returns {Object}
         */
        codSwitch: function (data) {
            const on = rules.amount(data.cod_amount) > 0;

            return {
                id: 'polkurier-service-cod',
                label: $t('Cash on delivery'),
                checked: on,
                amount: on ? this.money(data.cod_amount) : '',
                details: on ? [this.choice('cod_type', data.cod_type), this.choice('cod_return_type', data.cod_return_type)]
                    .filter(rules.filled).join(' · ') : '',
                problem: on && data.cod_return_type === 'BA' && !rules.filled(data.cod_bank_account_id)
                    ? $t('No bank account to pay it out to.')
                    : '',
                unavailable: this.features().cod === false ? $t('This carrier does not collect cash on delivery.') : '',
                edit: this.openModal.bind(this, this.codModal),
                toggle: this.toggleCod.bind(this)
            };
        },

        /**
         * @param {Object} data
         * @returns {Object}
         */
        insuranceSwitch: function (data) {
            const on = rules.amount(data.insurance_amount) > 0;

            return {
                id: 'polkurier-service-insurance',
                label: $t('Insurance'),
                checked: on,
                amount: on ? this.money(data.insurance_amount) : '',
                edit: this.openModal.bind(this, this.insuranceModal),
                toggle: this.toggleInsurance.bind(this)
            };
        },

        /**
         * @param {Boolean} on
         */
        toggleCod: function (on) {
            if (!on) {
                this.set('cod_amount', '');

                return;
            }
            // What the order's other Polkurier orders don't collect yet.
            if (rules.amount(this.get('cod_amount')) <= 0 && rules.amount(this.get('cod_remaining')) > 0) {
                this.set('cod_amount', rules.amount(this.get('cod_remaining')).toFixed(2));
            }
            this.openModal(this.codModal);
        },

        /**
         * @param {Boolean} on
         */
        toggleInsurance: function (on) {
            if (!on) {
                this.set('insurance_amount', '');

                return;
            }
            const value = Math.max(rules.amount(this.get('shipment_value')), rules.amount(this.get('cod_amount')));

            if (value > 0) {
                this.set('insurance_amount', value.toFixed(2));
            } else {
                this.openModal(this.insuranceModal);
            }
        },

        /**
         * @param {String} service
         * @param {Boolean} on
         */
        toggleService: function (service, on) {
            const services = _.without(this.get('services') || [], service);

            if (on) {
                services.push(service);
            }
            this.set('services', services);
        },

        /**
         * @param {Boolean} on
         */
        toggleCover: function (on) {
            const options = this.get('cover_addresses') || [];

            this.set('cover_address_id', on && options.length ? String(options[0].value) : '');
        },

        /**
         * @param {Object} item
         * @param {Event} event
         * @returns {Boolean}
         */
        onSwitch: function (item, event) {
            item.toggle(!!event.target.checked);

            return true;
        }
    });
});
