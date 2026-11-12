/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * Cash on delivery, insurance and the additional services available for the shipment's type, each
 * switched on and off in place, so they can be chosen before the carrier that offers them. When
 * switched on, cash on delivery and insurance take the amount they're known to need, or open their
 * modals for it to be entered; "Change" opens the modals for the details.
 */
define([
    'ko',
    'underscore',
    'mage/translate',
    './part',
    './rules'
], function (ko, _, $t, Part, rules) {
    'use strict';

    // The cover sender address: the service that prints the cover address on the label.
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
                features = this.getFeatures(),
                // What's available for the shipment's type, whichever carrier offers it.
                allowed = rules.findServices(data),
                offered = features.services || null,
                coverOffered = allowed === null || allowed.indexOf(COVER) !== -1,
                labels = (data.choices || {}).services || {},
                services = (data.services || []).slice(),
                switches = [];

            // Services not available for the shipment's type are dropped. A service the chosen
            // carrier doesn't offer is a problem with the carrier (rules.js). The cover sender
            // address is never kept among them: choosing a cover address below switches it on.
            const kept = services.filter(function (service) {
                return service !== COVER && (allowed === null || allowed.indexOf(service) !== -1);
            });

            if (kept.length !== services.length) {
                this.set('services', kept);

                return;
            }
            if (!coverOffered && rules.isFilled(data.cover_address_id)) {
                this.set('cover_address_id', '');

                return;
            }

            if (features.cod !== false || rules.parseAmount(data.cod_amount) > 0) {
                switches.push(this.buildCodSwitch(data));
            }
            if (features.insurance !== false || rules.parseAmount(data.insurance_amount) > 0) {
                switches.push(this.buildInsuranceSwitch(data));
            }
            _.each(labels, function (label, service) {
                if (allowed === null || allowed.indexOf(service) !== -1) {
                    switches.push({
                        id: 'polkurier-service-' + service,
                        label: label,
                        checked: services.indexOf(service) !== -1,
                        unavailable: offered && offered.indexOf(service) === -1
                            ? $t('This carrier does not offer it.')
                            : '',
                        toggle: this.toggleService.bind(this, service)
                    });
                }
            }, this);
            if (coverOffered && (data.cover_addresses || []).length) {
                switches.push({
                    id: 'polkurier-service-cover',
                    label: $t('Another sender on the label'),
                    checked: rules.isFilled(data.cover_address_id),
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
        buildCodSwitch: function (data) {
            const on = rules.parseAmount(data.cod_amount) > 0,
                unavailable = this.getFeatures().cod === false;

            return {
                id: 'polkurier-service-cod',
                label: $t('Cash on delivery'),
                checked: on,
                amount: on ? this.formatMoney(data.cod_amount) : '',
                details: on
                    ? [
                        this.getChoiceLabel('cod_type', data.cod_type),
                        this.getChoiceLabel('cod_return_type', data.cod_return_type)
                    ]
                        .filter(rules.isFilled)
                        .join(' · ')
                    : '',
                problem: on && data.cod_return_type === 'BA' && (!rules.isFilled(data.cod_bank_account_id)
                    || (data.cod_bank_account_id === 'new' && !rules.isFilled(data.new_bank_account_number)))
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
        buildInsuranceSwitch: function (data) {
            const on = rules.parseAmount(data.insurance_amount) > 0,
                unavailable = this.getFeatures().insurance === false;

            return {
                id: 'polkurier-service-insurance',
                label: $t('Insurance'),
                checked: on,
                amount: on ? this.formatMoney(data.insurance_amount) : '',
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
            const value = rules.parseAmount(this.get('cod_remaining'));

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
            // What it collects, or else what it's worth (rules.calculateInsurance).
            const value = rules.calculateInsurance(
                rules.parseAmount(this.get('cod_amount')),
                rules.parseAmount(this.get('shipment_value'))
            );

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
        },

        /**
         * A click anywhere on a service's tile flips its switch, except a click on something in
         * the tile that acts by itself: the switch and its caption, "Change", the address list.
         *
         * @param {Object} item
         * @param {Event} event
         * @returns {Boolean} true, to leave the click to what it landed on
         */
        onTile: function (item, event) {
            if (!event.target.closest('input, label, button, select, a')) {
                event.currentTarget.querySelector('.admin__actions-switch-checkbox').click();
            }

            return true;
        }
    });
});
