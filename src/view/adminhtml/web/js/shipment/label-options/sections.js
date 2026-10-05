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
         * @param {String} choice the select whose "new" shows the fields
         * @returns {Function} whether the field shows in the group's fields
         */
        /**
         * @returns {String} whether the carrier takes the parcels at a point: NONE, OPTIONAL or REQUIRED
         */
        function senderPoint() {
            const c = carrier();

            return c && c.sender_point ? c.sender_point : 'NONE';
        }

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
                    if (filled(values.carrier_code) && !filled(config.carriersError) && !carrier()) {
                        return $t('This carrier is no longer available: choose another.');
                    }

                    return filled(config.carriersError)
                        ? $t('The carrier list could not be read, so what the carrier supports isn\'t checked here: %1').replace('%1', config.carriersError)
                        : '';
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
                    if (senderPoint() === 'REQUIRED' && !filled(values.sender_point_id)) {
                        return $t('This carrier takes the parcels at a point: choose the drop-off point you hand them over at.');
                    }

                    return values.sender_address_id === 'new' && rules.newSenderLacking(values).length
                        ? $t('Fill in the sender\'s %1.').replace('%1', rules.newSenderLacking(values).join(', '))
                        : '';
                },
                summary: function () {
                    // Handed over at a point, for a carrier that takes parcels at points.
                    const point = senderPoint() !== 'NONE' && filled(values.sender_point_id)
                        ? ' · ' + $t('drop-off point %1').replace('%1', values.sender_point_id)
                        : '';

                    if (values.sender_address_id !== 'new') {
                        return label('sender_address_id', values.sender_address_id || '') + point;
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
                    ].join(' · ') + point;
                },
                fields: [
                    {name: 'sender_address_id', label: $t('Sender'), type: 'select'},
                    {
                        name: 'sender_point_id',
                        label: $t('Drop-off point'),
                        type: 'text',
                        map: true,
                        mapSearch: state.senderPostcode,
                        visible: function () {
                            return senderPoint() !== 'NONE';
                        },
                        note: $t('Where you hand the parcels over, for a carrier that takes them at points.')
                    }
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
                    const c = carrier();

                    return c && values.pickup_mode === 'courier' && (
                        (c.pickup_date_required && !filled(values.pickup_date))
                        || (c.pickup_time_required && !(filled(values.pickup_time_from) && filled(values.pickup_time_to)))
                    )
                        ? $t('Choose when the courier collects the parcels.')
                        : '';
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
                    {name: 'pickup_slot', label: $t('Collection date'), type: 'pickup', visible: pickupDateVisible, shownFor: courierPickup}
                ]
            },
            {
                title: $t('Pickup point'),
                visible: function () {
                    return carrier() && carrier().point !== 'NONE';
                },
                warning: function () {
                    if (carrier() && carrier().point === 'REQUIRED' && !filled(values.recipient_point_id)) {
                        return $t('This carrier delivers to pickup points only: choose one.');
                    }

                    return filled(values.recipient_point_id) && values.recipient_point_id === closedPoint
                        ? $t('Pickup point %1 is closed or no longer exists: choose another.').replace('%1', values.recipient_point_id)
                        : '';
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
                    if (state.codTooHigh()) {
                        return $t('Cash on delivery is more than the %1 left to collect.').replace('%1', money(config.codRemaining));
                    }

                    return filled(values.cod_amount) && values.cod_return_type === 'BA' && values.cod_bank_account_id === 'new'
                        && !filled(values.new_bank_account_number)
                        ? $t('Enter the number of the bank account the cash on delivery is paid out to.')
                        : '';
                },
                summary: function () {
                    if (!filled(values.cod_amount)) {
                        return $t('None');
                    }

                    return [
                        money(values.cod_amount),
                        label('cod_type', values.cod_type),
                        values.cod_bank_account_id === 'new'
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
                    {name: 'cod_bank_account_id', label: $t('Bank account'), type: 'select'},
                    {name: 'new_bank_account_number', label: $t('Account number'), type: 'text', shownFor: typingIn('cod_bank_account_id')},
                    {name: 'keep_new_bank_account', label: $t('Save to your bank accounts for later shipments'), type: 'checkbox', shownFor: typingIn('cod_bank_account_id')},
                    {
                        name: 'new_bank_account_name',
                        label: $t('Saved as'),
                        type: 'text',
                        note: $t('If left empty: "Account ending" and its last four digits. Saved once the shipment is placed; Polkurier takes it once you confirm it with the code or your security PIN.'),
                        shownFor: keeping('cod_bank_account_id', 'keep_new_bank_account')
                    }
                ]
            },
            {
                title: $t('Insurance'),
                visible: function () {
                    return carrier() && carrier().insurance;
                },
                warning: function () {
                    return state.insuranceTooLow()
                        ? $t('With cash on delivery, this carrier needs the shipment insured for at least %1.').replace('%1', money(values.cod_amount))
                        : '';
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
                    return carrier() ? rules.servicesLacking(values, carrier().services).map(function (service) {
                        return $t('This carrier does not offer "%1".').replace('%1', service === 'COVER_ADDRESS_SENDER'
                            ? $t('Another sender on the label')
                            : label('services', service) || service);
                    }) : [];
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
