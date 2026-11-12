/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * The groups of label options: for each, its one-line summary, when it's shown or needs
 * attention, and the fields it's edited with.
 */
define([
    'mage/translate',
    'Polkurier_Shipping/js/shipment/workspace/rules',
    'Polkurier_Shipping/js/shipment/points-map'
], function ($t, rules, pointsMap) {
    'use strict';

    /**
     * @param {Object} state see ./state
     * @param {Object} config the page's label options
     * @returns {Array<Object>}
     */
    return function (state, config) {
        const values = state.values,
            isFilled = state.isFilled,
            getCarrier = state.getCarrier,
            label = state.getOptionLabel,
            formatMoney = state.formatMoney,
            closedPoint = config.point && config.point.closed ? config.point.id : '';

        /**
         * @returns {String} whether the carrier takes the parcels at a point: NONE, OPTIONAL or REQUIRED
         */
        function getSenderPointRequirement() {
            const c = getCarrier();

            return c && c.sender_point ? c.sender_point : 'NONE';
        }

        /**
         * @param {String} mode how the parcels get to the carrier
         * @returns {Boolean} whether the drop-off point counts
         */
        function isSenderPointUsed(mode) {
            const c = getCarrier();

            return rules.isSenderPointUsed(getSenderPointRequirement(), c ? c.drop_off : true, mode);
        }

        /**
         * @param {String} choice the select whose "new" shows the fields
         * @returns {Function} whether the field shows in the group's fields
         */
        function createTypingInCheck(choice) {
            return function (form) {
                return form.find('[name=' + choice + ']').val() === 'new';
            };
        }

        /**
         * @param {String} choice
         * @param {String} keep the checkbox saving it for later
         * @returns {Function}
         */
        function createKeepingCheck(choice, keep) {
            return function (form) {
                return createTypingInCheck(choice)(form) && form.find('[name=' + keep + ']').prop('checked');
            };
        }

        /**
         * @param {String} field
         * @returns {Boolean} whether what's typed in is saved for later
         */
        function isKept(field) {
            return isFilled(values[field]) && String(values[field]) !== '0' && values[field] !== false;
        }

        /**
         * The sender typed in, field by field, shown while "A new address" is chosen.
         *
         * @returns {Array<Object>}
         */
        function buildNewSenderFields() {
            const shown = createTypingInCheck('sender_address_id');

            return [
                {name: 'new_sender_person', label: $t('Name'), type: 'text'},
                {name: 'new_sender_company', label: $t('Company'), type: 'text'},
                {name: 'new_sender_street', label: $t('Street'), type: 'text'},
                {name: 'new_sender_house_number', label: $t('House number'), type: 'text'},
                {name: 'new_sender_flat_number', label: $t('Flat number'), type: 'text'},
                {name: 'new_sender_postcode', label: $t('Postcode'), type: 'text'},
                {name: 'new_sender_city', label: $t('City'), type: 'text'},
                {
                    name: 'new_sender_country',
                    label: $t('Country'),
                    type: 'text',
                    note: $t('Its two-letter code; PL if left empty.')
                },
                {name: 'new_sender_phone', label: $t('Phone'), type: 'tel'},
                {name: 'new_sender_email', label: $t('E-mail'), type: 'email'}
            ].map(function (field) {
                return Object.assign(field, {isShownFor: shown});
            });
        }

        /**
         * The bank account applies only to cash on delivery paid out to a bank account.
         *
         * @param {jQuery} form the group's fields, as edited
         * @returns {Boolean}
         */
        function isReturnedToBankAccount(form) {
            return form.find('[name=cod_return_type]').val() === 'BA';
        }

        function isBankAccountTypedIn(form) {
            return isReturnedToBankAccount(form) && createTypingInCheck('cod_bank_account_id')(form);
        }

        function isPickupDateVisible() {
            return getCarrier() && getCarrier().pickup_date;
        }

        /**
         * A collection date only means something when a courier collects the parcels.
         *
         * @param {jQuery} form the group's fields, as edited
         * @returns {Boolean}
         */
        function isCourierPickup(form) {
            return form.find('[name=pickup_mode]').val() === 'courier';
        }

        return [
            {
                title: $t('Carrier'),
                getWarning: function () {
                    return state.findIssues(['carrier_code']);
                },
                getSummary: function () {
                    return label('carrier_code', values.carrier_code) || $t('Not chosen');
                },
                fields: [
                    {name: 'carrier_code', label: $t('Carrier'), type: 'select'}
                ]
            },
            {
                title: $t('Shipment description'),
                getSummary: function () {
                    return isFilled(values.description) ? values.description : $t('None');
                },
                fields: [
                    {
                        name: 'description',
                        label: $t('Description'),
                        type: 'text',
                        maxlength: 30,
                        note: $t('Printed on the label; up to 30 characters. It starts as the order number, as the shipment description format in the configuration says.')
                    }
                ]
            },
            {
                title: $t('Sender'),
                getWarning: function () {
                    return state.findIssues(['sender_address_id']);
                },
                getSummary: function () {
                    if (values.sender_address_id !== 'new') {
                        return label('sender_address_id', values.sender_address_id || '');
                    }
                    const number = [values.new_sender_house_number, values.new_sender_flat_number]
                            .filter(filled)
                            .join('/'),
                        address = [
                            values.new_sender_company || values.new_sender_person,
                            [values.new_sender_street, number].filter(filled).join(' '),
                            [values.new_sender_postcode, values.new_sender_city].filter(filled).join(' ')
                        ].filter(filled).join(', ');

                    return [
                        address || $t('A new address'),
                        isKept('keep_new_sender')
                            ? $t('saved to your addresses once placed')
                            : $t('for this shipment only')
                    ].join(' · ');
                },
                fields: [
                    {name: 'sender_address_id', label: $t('Sender'), type: 'select'}
                ].concat(buildNewSenderFields(), [
                    {
                        name: 'keep_new_sender',
                        label: $t('Save to your addresses for later shipments'),
                        type: 'checkbox',
                        isShownFor: createTypingInCheck('sender_address_id')
                    },
                    {
                        name: 'new_sender_name',
                        label: $t('Saved as'),
                        type: 'text',
                        note: $t('If left empty: the company or the name, and the city. Saved once the shipment is placed.'),
                        isShownFor: createKeepingCheck('sender_address_id', 'keep_new_sender')
                    }
                ])
            },
            {
                title: $t('Handover'),
                getWarning: function () {
                    return state.findIssues(['pickup_mode', 'pickup_date', 'pickup_time_from', 'sender_point_id']);
                },
                getSummary: function () {
                    let text = label('pickup_mode', values.pickup_mode);

                    if (values.pickup_mode === 'courier' && isFilled(values.pickup_date)) {
                        text += ', ' + values.pickup_date;
                        if (isFilled(values.pickup_time_from) && isFilled(values.pickup_time_to)) {
                            text += ' ' + values.pickup_time_from + '–' + values.pickup_time_to;
                        }
                    } else if (values.pickup_mode === 'courier' && getCarrier() && getCarrier().pickup_date_required) {
                        text += ', ' + $t('collection date required');
                    }
                    // Handed over at a point, for a carrier that takes parcels at points.
                    if (isSenderPointUsed(values.pickup_mode) && isFilled(values.sender_point_id)) {
                        text += ' · ' + $t('drop-off point %1').replace('%1', [
                            values.sender_point_id,
                            pointsMap.describe(config.pointDetails, values.sender_point_id)
                        ].filter(Boolean).join(' — '));
                    }

                    return text;
                },
                fields: [
                    {
                        name: 'pickup_mode',
                        label: $t('Handover'),
                        type: 'select',
                        filter: function (value) {
                            return rules.isHandoverOffered(value, rules.buildFeatures(getCarrier()));
                        }
                    },
                    // The dates and windows Polkurier offers for the carrier.
                    {
                        name: 'pickup_slot',
                        label: $t('Collection date'),
                        type: 'pickup',
                        isVisible: isPickupDateVisible,
                        isShownFor: isCourierPickup
                    },
                    {
                        name: 'sender_point_id',
                        label: $t('Drop-off point'),
                        type: 'text',
                        map: true,
                        mapSearch: state.getSenderPostcode,
                        // Points that take parcels for sending.
                        mapFunctions: ['send'],
                        isVisible: function () {
                            return getSenderPointRequirement() !== 'NONE';
                        },
                        isShownFor: function (form) {
                            return isSenderPointUsed(form.find('[name=pickup_mode]').val());
                        },
                        note: $t('Where you hand the parcels over, for a carrier that takes them at points.')
                    }
                ]
            },
            {
                title: $t('Pickup point'),
                isVisible: function () {
                    return getCarrier() && getCarrier().point !== 'NONE';
                },
                getWarning: function () {
                    return state.findIssues(['recipient_point_id']);
                },
                getSummary: function () {
                    const id = values.recipient_point_id;

                    if (isFilled(id)) {
                        // Its name and address, as Polkurier or the map has them; a code typed in
                        // has none.
                        return [id, pointsMap.describe(config.pointDetails, id)].filter(Boolean).join(' — ')
                            + (id === closedPoint ? '. ' + $t('Closed or no longer there: choose another point.') : '');
                    }

                    return getCarrier().point === 'REQUIRED' ? $t('Required, not chosen') : $t('None');
                },
                fields: [
                    {name: 'recipient_point_id', label: $t('Point code'), type: 'text', map: true}
                ]
            },
            {
                title: $t('Cash on delivery'),
                isVisible: function () {
                    return getCarrier() && getCarrier().cod;
                },
                getWarning: function () {
                    return state.findIssues(['cod_amount', 'cod_bank_account_id', 'new_bank_account_number']);
                },
                getSummary: function () {
                    if (!isFilled(values.cod_amount)) {
                        return $t('None');
                    }

                    return [
                        formatMoney(values.cod_amount),
                        label('cod_type', values.cod_type),
                        values.cod_return_type !== 'BA' ? label('cod_return_type', values.cod_return_type) : '',
                        values.cod_return_type !== 'BA' ? '' : values.cod_bank_account_id === 'new'
                            ? [
                                values.new_bank_account_number || $t('A new bank account'),
                                isKept('keep_new_bank_account') ? $t('saved to your bank accounts once placed') : ''
                            ].filter(filled).join(', ')
                            : label('cod_bank_account_id', values.cod_bank_account_id),
                        state.isCodTooHigh()
                            ? $t('more than the %1 left to collect').replace('%1', formatMoney(config.codRemaining))
                            : ''
                    ].filter(Boolean).join(' · ');
                },
                // Normalized again after the cash on delivery changes: insurance may have to follow.
                normalizes: true,
                fields: [
                    {name: 'cod_amount', label: $t('Amount to collect'), type: 'number', note: state.getCodNote},
                    {name: 'cod_type', label: $t('Payout time'), type: 'select'},
                    {name: 'cod_return_type', label: $t('Paid out to'), type: 'select'},
                    {
                        name: 'cod_bank_account_id',
                        label: $t('Bank account'),
                        type: 'select',
                        isShownFor: isReturnedToBankAccount
                    },
                    {
                        name: 'new_bank_account_number',
                        label: $t('Account number'),
                        type: 'text',
                        isShownFor: isBankAccountTypedIn
                    },
                    {
                        name: 'keep_new_bank_account',
                        label: $t('Save to your bank accounts for later shipments'),
                        type: 'checkbox',
                        isShownFor: isBankAccountTypedIn
                    },
                    {
                        name: 'new_bank_account_name',
                        label: $t('Saved as'),
                        type: 'text',
                        note: $t('If left empty: "Account ending" and its last four digits. Saved once the shipment is placed; Polkurier takes it once you confirm it with the code or your security PIN.'),
                        isShownFor: function (form) {
                            return isReturnedToBankAccount(form)
                                && createKeepingCheck('cod_bank_account_id', 'keep_new_bank_account')(form);
                        }
                    }
                ]
            },
            {
                title: $t('Insurance'),
                isVisible: function () {
                    return getCarrier() && getCarrier().insurance;
                },
                getWarning: function () {
                    return state.findIssues(['insurance_amount']);
                },
                getSummary: function () {
                    return isFilled(values.insurance_amount)
                        ? $t('Insured for %1').replace('%1', formatMoney(values.insurance_amount))
                        : $t('Not insured');
                },
                fields: [
                    {
                        name: 'insurance_amount',
                        label: $t('Declared value'),
                        type: 'number',
                        note: state.getInsuranceNote
                    }
                ]
            },
            {
                title: $t('Additional services'),
                getWarning: function () {
                    return state.findIssues(['services']);
                },
                getSummary: function () {
                    const parts = [];

                    values.services.forEach(function (service) {
                        parts.push(label('services', service));
                    });
                    if (isFilled(values.cover_address_id)) {
                        parts.push(
                            $t('sender on the label: %1')
                                .replace('%1', label('cover_address_id', values.cover_address_id))
                        );
                    }

                    return parts.length ? parts.join(' · ') : $t('None');
                },
                fields: [
                    {
                        name: 'services',
                        label: $t('Additional services'),
                        type: 'checkboxes',
                        // Offered when any carrier offers it; one the chosen carrier doesn't offer
                        // is a problem.
                        filter: function (value) {
                            return state.isOfferedByAnyCarrier(value);
                        }
                    },
                    {
                        // The cover sender address, with the address it prints.
                        name: 'cover_address_id',
                        label: $t('Another sender on the label'),
                        type: 'select',
                        isVisible: function () {
                            return state.isOfferedByAnyCarrier('COVER_ADDRESS_SENDER')
                                && (config.options.cover_address_id || []).length > 1;
                        }
                    }
                ]
            }
        ];
    };
});
