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
                summary: function () {
                    return label('sender_address_id', values.sender_address_id || '');
                },
                fields: [
                    {name: 'sender_address_id', label: $t('Sender'), type: 'select'}
                ]
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
                    return state.codTooHigh()
                        ? $t('Cash on delivery is more than the %1 left to collect.').replace('%1', money(config.codRemaining))
                        : '';
                },
                summary: function () {
                    if (!filled(values.cod_amount)) {
                        return $t('None');
                    }

                    return [
                        money(values.cod_amount),
                        label('cod_type', values.cod_type),
                        label('cod_bank_account_id', values.cod_bank_account_id),
                        state.codTooHigh() ? $t('more than the %1 left to collect').replace('%1', money(config.codRemaining)) : ''
                    ].filter(Boolean).join(' · ');
                },
                // Asked for again after the cash on delivery changes: insurance may have to follow.
                normalizes: true,
                fields: [
                    {name: 'cod_amount', label: $t('Amount to collect'), type: 'number', note: state.codNote},
                    {name: 'cod_type', label: $t('Payout time'), type: 'select'},
                    {name: 'cod_return_type', label: $t('Paid out to'), type: 'select'},
                    {name: 'cod_bank_account_id', label: $t('Bank account'), type: 'select'}
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
