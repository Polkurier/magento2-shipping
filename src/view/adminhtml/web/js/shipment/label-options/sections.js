/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The groups of label options: for each, its one-line summary, when it's shown or needs
 * attention, and the fields it's edited with.
 */
define(['mage/translate', 'Polkurier_Shipping/js/shipment/workspace/rules'], function ($t, rules) {
    'use strict';

    /**
     * @param {Object} state see ./state
     * @param {Object} config the page's label options
     * @returns {Array<Object>}
     */
    return function (state, config) {
        const values = state.values,
            filled = state.filled,
            carrier = state.carrier,
            label = state.optionLabel,
            money = state.money,
            // Point addresses by code, shared with the editor, which adds what's chosen on the map.
            pointLabels = config.pointLabels = config.pointLabels || {},
            closedPoint = config.point && config.point.closed ? config.point.id : '';

        /**
         * @returns {String} whether the carrier takes the parcels at a point: NONE, OPTIONAL or REQUIRED
         */
        function senderPoint() {
            const c = carrier();

            return c && c.sender_point ? c.sender_point : 'NONE';
        }

        /**
         * @param {String} mode how the parcels get to the carrier
         * @returns {Boolean} whether the drop-off point counts
         */
        function senderPointUsed(mode) {
            const c = carrier();

            return rules.senderPointUsed(senderPoint(), c ? c.drop_off : true, mode);
        }

        /**
         * @param {String} choice the select whose "new" shows the fields
         * @returns {Function} whether the field shows in the group's fields
         */
        function typingIn(choice) {
            return function (form) {
                return form.find('[name=' + choice + ']').val() === 'new';
            };
        }

        /**
         * @param {String} choice
         * @param {String} keep the checkbox saving it for later
         * @returns {Function}
         */
        function keeping(choice, keep) {
            return function (form) {
                return typingIn(choice)(form) && form.find('[name=' + keep + ']').prop('checked');
            };
        }

        /**
         * @param {String} field
         * @returns {Boolean} whether what's typed in is saved for later
         */
        function kept(field) {
            return filled(values[field]) && String(values[field]) !== '0' && values[field] !== false;
        }

        /**
         * The sender typed in, field by field, shown while "A new address" is chosen.
         *
         * @returns {Array<Object>}
         */
        function newSenderFields() {
            const shown = typingIn('sender_address_id');

            return [
                {name: 'new_sender_person', label: $t('Name'), type: 'text'},
                {name: 'new_sender_company', label: $t('Company'), type: 'text'},
                {name: 'new_sender_street', label: $t('Street'), type: 'text'},
                {name: 'new_sender_house_number', label: $t('House number'), type: 'text'},
                {name: 'new_sender_flat_number', label: $t('Flat number'), type: 'text'},
                {name: 'new_sender_postcode', label: $t('Postcode'), type: 'text'},
                {name: 'new_sender_city', label: $t('City'), type: 'text'},
                {name: 'new_sender_country', label: $t('Country'), type: 'text', note: $t('Its two-letter code; PL if left empty.')},
                {name: 'new_sender_phone', label: $t('Phone'), type: 'tel'},
                {name: 'new_sender_email', label: $t('E-mail'), type: 'email'}
            ].map(function (field) {
                return Object.assign(field, {shownFor: shown});
            });
        }

        /**
         * The bank account only for cash on delivery paid out to one.
         *
         * @param {jQuery} form the group's fields, as edited
         * @returns {Boolean}
         */
        function toBankAccount(form) {
            return form.find('[name=cod_return_type]').val() === 'BA';
        }

        function bankAccountTypedIn(form) {
            return toBankAccount(form) && typingIn('cod_bank_account_id')(form);
        }

        if (config.point && config.point.id && config.point.label) {
            pointLabels[config.point.id] = config.point.label;
        }

        function pickupDateVisible() {
            return carrier() && carrier().pickup_date;
        }

        /**
         * A collection date only means something when a courier collects the parcels.
         *
         * @param {jQuery} form the group's fields, as edited
         * @returns {Boolean}
         */
        function courierPickup(form) {
            return form.find('[name=pickup_mode]').val() === 'courier';
        }

        return [
            {
                title: $t('Carrier'),
                warning: function () {
                    return state.issues(['carrier_code']);
                },
                summary: function () {
                    return label('carrier_code', values.carrier_code) || $t('Not chosen');
                },
                fields: [
                    {name: 'carrier_code', label: $t('Carrier'), type: 'select'}
                ]
            },
            {
                title: $t('Shipment description'),
                summary: function () {
                    return filled(values.description) ? values.description : $t('None');
                },
                fields: [
                    {name: 'description', label: $t('Description'), type: 'text', maxlength: 30, note: $t('Printed on the label; up to 30 characters. It starts as the order number, as the shipment description format in the configuration says.')}
                ]
            },
            {
                title: $t('Sender'),
                warning: function () {
                    return state.issues(['sender_address_id']);
                },
                summary: function () {
                    if (values.sender_address_id !== 'new') {
                        return label('sender_address_id', values.sender_address_id || '');
                    }
                    const number = [values.new_sender_house_number, values.new_sender_flat_number].filter(filled).join('/'),
                        address = [
                            values.new_sender_company || values.new_sender_person,
                            [values.new_sender_street, number].filter(filled).join(' '),
                            [values.new_sender_postcode, values.new_sender_city].filter(filled).join(' ')
                        ].filter(filled).join(', ');

                    return [
                        address || $t('A new address'),
                        kept('keep_new_sender') ? $t('saved to your addresses once placed') : $t('for this shipment only')
                    ].join(' · ');
                },
                fields: [
                    {name: 'sender_address_id', label: $t('Sender'), type: 'select'}
                ].concat(newSenderFields(), [
                    {name: 'keep_new_sender', label: $t('Save to your addresses for later shipments'), type: 'checkbox', shownFor: typingIn('sender_address_id')},
                    {
                        name: 'new_sender_name',
                        label: $t('Saved as'),
                        type: 'text',
                        note: $t('If left empty: the company or the name, and the city. Saved once the shipment is placed.'),
                        shownFor: keeping('sender_address_id', 'keep_new_sender')
                    }
                ])
            },
            {
                title: $t('Handover'),
                warning: function () {
                    return state.issues(['pickup_mode', 'pickup_date', 'pickup_time_from', 'sender_point_id']);
                },
                summary: function () {
                    let text = label('pickup_mode', values.pickup_mode);

                    if (values.pickup_mode === 'courier' && filled(values.pickup_date)) {
                        text += ', ' + values.pickup_date;
                        if (filled(values.pickup_time_from) && filled(values.pickup_time_to)) {
                            text += ' ' + values.pickup_time_from + '–' + values.pickup_time_to;
                        }
                    } else if (values.pickup_mode === 'courier' && carrier() && carrier().pickup_date_required) {
                        text += ', ' + $t('collection date required');
                    }
                    // Handed over at a point, for a carrier that takes parcels at points.
                    if (senderPointUsed(values.pickup_mode) && filled(values.sender_point_id)) {
                        text += ' · ' + $t('drop-off point %1').replace('%1', values.sender_point_id);
                    }

                    return text;
                },
                fields: [
                    {
                        name: 'pickup_mode',
                        label: $t('Handover'),
                        type: 'select',
                        filter: function (value) {
                            return value !== 'drop_off' || (carrier() && carrier().drop_off);
                        }
                    },
                    // The dates and windows Polkurier offers for the carrier.
                    {name: 'pickup_slot', label: $t('Collection date'), type: 'pickup', visible: pickupDateVisible, shownFor: courierPickup},
                    {
                        name: 'sender_point_id',
                        label: $t('Drop-off point'),
                        type: 'text',
                        map: true,
                        mapSearch: state.senderPostcode,
                        visible: function () {
                            return senderPoint() !== 'NONE';
                        },
                        shownFor: function (form) {
                            return senderPointUsed(form.find('[name=pickup_mode]').val());
                        },
                        note: $t('Where you hand the parcels over, for a carrier that takes them at points.')
                    }
                ]
            },
            {
                title: $t('Pickup point'),
                visible: function () {
                    return carrier() && carrier().point !== 'NONE';
                },
                warning: function () {
                    return state.issues(['recipient_point_id']);
                },
                summary: function () {
                    const id = values.recipient_point_id;

                    if (filled(id)) {
                        // Its address, as the map or the order has it; a code typed in has none.
                        return [id, pointLabels[id] || ''].filter(Boolean).join(' — ')
                            + (id === closedPoint ? '. ' + $t('Closed or no longer there: choose another point.') : '');
                    }

                    return carrier().point === 'REQUIRED' ? $t('Required, not chosen') : $t('None');
                },
                fields: [
                    {name: 'recipient_point_id', label: $t('Point code'), type: 'text', map: true}
                ]
            },
            {
                title: $t('Cash on delivery'),
                visible: function () {
                    return carrier() && carrier().cod;
                },
                warning: function () {
                    return state.issues(['cod_amount', 'cod_bank_account_id', 'new_bank_account_number']);
                },
                summary: function () {
                    if (!filled(values.cod_amount)) {
                        return $t('None');
                    }

                    return [
                        money(values.cod_amount),
                        label('cod_type', values.cod_type),
                        values.cod_return_type !== 'BA' ? label('cod_return_type', values.cod_return_type) : '',
                        values.cod_return_type !== 'BA' ? '' : values.cod_bank_account_id === 'new'
                            ? [values.new_bank_account_number || $t('A new bank account'),
                                kept('keep_new_bank_account') ? $t('saved to your bank accounts once placed') : ''].filter(filled).join(', ')
                            : label('cod_bank_account_id', values.cod_bank_account_id),
                        state.codTooHigh() ? $t('more than the %1 left to collect').replace('%1', money(config.codRemaining)) : ''
                    ].filter(Boolean).join(' · ');
                },
                // Asked for again after the cash on delivery changes: insurance may have to follow.
                normalizes: true,
                fields: [
                    {name: 'cod_amount', label: $t('Amount to collect'), type: 'number', note: state.codNote},
                    {name: 'cod_type', label: $t('Payout time'), type: 'select'},
                    {name: 'cod_return_type', label: $t('Paid out to'), type: 'select'},
                    {name: 'cod_bank_account_id', label: $t('Bank account'), type: 'select', shownFor: toBankAccount},
                    {name: 'new_bank_account_number', label: $t('Account number'), type: 'text', shownFor: bankAccountTypedIn},
                    {
                        name: 'keep_new_bank_account',
                        label: $t('Save to your bank accounts for later shipments'),
                        type: 'checkbox',
                        shownFor: bankAccountTypedIn
                    },
                    {
                        name: 'new_bank_account_name',
                        label: $t('Saved as'),
                        type: 'text',
                        note: $t('If left empty: "Account ending" and its last four digits. Saved once the shipment is placed; Polkurier takes it once you confirm it with the code or your security PIN.'),
                        shownFor: function (form) {
                            return toBankAccount(form) && keeping('cod_bank_account_id', 'keep_new_bank_account')(form);
                        }
                    }
                ]
            },
            {
                title: $t('Insurance'),
                visible: function () {
                    return carrier() && carrier().insurance;
                },
                warning: function () {
                    return state.issues(['insurance_amount']);
                },
                summary: function () {
                    return filled(values.insurance_amount)
                        ? $t('Insured for %1').replace('%1', money(values.insurance_amount))
                        : $t('Not insured');
                },
                fields: [
                    {
                        name: 'insurance_amount',
                        label: $t('Declared value'),
                        type: 'number',
                        note: state.insuranceNote
                    }
                ]
            },
            {
                title: $t('Additional services'),
                warning: function () {
                    return state.issues(['services']);
                },
                summary: function () {
                    const parts = [];

                    values.services.forEach(function (service) {
                        parts.push(label('services', service));
                    });
                    if (filled(values.cover_address_id)) {
                        parts.push($t('sender on the label: %1').replace('%1', label('cover_address_id', values.cover_address_id)));
                    }

                    return parts.length ? parts.join(' · ') : $t('None');
                },
                fields: [
                    {
                        name: 'services',
                        label: $t('Additional services'),
                        type: 'checkboxes',
                        // Whichever carrier offers it: one the chosen carrier doesn't is a problem.
                        filter: function (value) {
                            return state.anyCarrierOffers(value);
                        }
                    },
                    {
                        // The cover sender address, with the address it prints.
                        name: 'cover_address_id',
                        label: $t('Another sender on the label'),
                        type: 'select',
                        visible: function () {
                            return state.anyCarrierOffers('COVER_ADDRESS_SENDER') && (config.options.cover_address_id || []).length > 1;
                        }
                    }
                ]
            }
        ];
    };
});
