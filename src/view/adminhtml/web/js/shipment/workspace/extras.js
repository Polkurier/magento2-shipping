/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Cash on delivery, insurance and the additional services there are for the shipment's type, each
 * switched on and off in place, so they can be chosen before the carrier that offers them. Cash on delivery and insurance switched on take the amount they're known to need, or
 * open their modals to have it entered; "Change" opens them for the details.
 */
define([
    'ko',
    'underscore',
    'mage/translate',
    './part',
    './rules'
], function (ko, _, $t, Part, rules) {
    'use strict';

    // The cover sender address: what printing the cover address on the label is, as a service.
    const COVER = 'COVER_ADDRESS_SENDER';

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
        },

        refresh: function () {
            const data = this.get() || {},
                features = this.features(),
                // What there is for the shipment's type, whichever carrier offers it.
                allowed = rules.servicesFor(data),
                offered = features.services || null,
                coverOffered = allowed === null || allowed.indexOf(COVER) !== -1,
                labels = (data.choices || {}).services || {},
                services = (data.services || []).slice(),
                switches = [];

            // Services there are none of for the shipment's type go. The chosen carrier not
            // offering one is a problem with the carrier (rules.js). The cover sender address is
            // never one: the cover address chosen below switches it on.
            const kept = services.filter(function (service) {
                return service !== COVER && (allowed === null || allowed.indexOf(service) !== -1);
            });

            if (kept.length !== services.length) {
                this.set('services', kept);

                return;
            }
            if (!coverOffered && rules.filled(data.cover_address_id)) {
                this.set('cover_address_id', '');

                return;
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
                        unavailable: offered && offered.indexOf(service) === -1 ? $t('This carrier does not offer it.') : '',
                        toggle: this.toggleService.bind(this, service)
                    });
                }
            }, this);
            if (coverOffered && (data.cover_addresses || []).length) {
                switches.push({
                    id: 'polkurier-service-cover',
                    label: $t('Another sender on the label'),
                    checked: rules.filled(data.cover_address_id),
                    unavailable: offered && offered.indexOf(COVER) === -1 ? $t('This carrier does not offer it.') : '',
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
            const on = rules.amount(data.cod_amount) > 0,
                unavailable = this.features().cod === false;

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
                unavailable: unavailable ? $t('This carrier does not collect cash on delivery.') : '',
                edit: unavailable ? null : this.openModal.bind(this, this.codModal),
                toggle: this.toggleCod.bind(this)
            };
        },

        /**
         * @param {Object} data
         * @returns {Object}
         */
        insuranceSwitch: function (data) {
            const on = rules.amount(data.insurance_amount) > 0,
                unavailable = this.features().insurance === false;

            return {
                id: 'polkurier-service-insurance',
                label: $t('Insurance'),
                checked: on,
                amount: on ? this.money(data.insurance_amount) : '',
                unavailable: unavailable ? $t('This carrier does not insure shipments.') : '',
                edit: unavailable ? null : this.openModal.bind(this, this.insuranceModal),
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
            const value = rules.amount(this.get('cod_remaining'));

            if (value > 0) {
                this.set('cod_amount', value.toFixed(2));
            } else {
                this.openModal(this.codModal);
            }
        },

        /**
         * @param {Boolean} on
         */
        toggleInsurance: function (on) {
            if (!on) {
                this.set('insurance_amount', '');

                return;
            }
            // What it collects, or else what it's worth (rules.insuranceFor).
            const value = rules.insuranceFor(rules.amount(this.get('cod_amount')), rules.amount(this.get('shipment_value')));

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
