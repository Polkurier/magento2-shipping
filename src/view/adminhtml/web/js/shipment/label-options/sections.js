/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The groups of label options: for each, its one-line summary, when it's shown or needs
 * attention, and the fields of its modal.
 */
define(['mage/translate'], function ($t) {
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
            money = state.money;

        function pickupDateVisible() {
            return carrier() && carrier().pickup_date;
        }

        /**
         * A collection date only means something when a courier collects the parcels.
         *
         * @param {jQuery} form the modal's form
         * @returns {Boolean}
         */
        function courierPickup(form) {
            return form.find('[name=pickup_mode]').val() === 'courier';
        }

        return [
            {
                title: $t('Carrier'),
                warning: function () {
                    return filled(config.carriersError);
                },
                summary: function () {
                    const parts = [label('carrier_code', values.carrier_code) || $t('Not chosen')];

                    if (filled(config.carriersError)) {
                        parts.push($t('carrier list unavailable: %1').replace('%1', config.carriersError));
                    }

                    if (filled(values.shipment_type)) {
                        parts.push(label('shipment_type', values.shipment_type));
                    }
                    parts.push($t('from %1').replace('%1', label('sender_address_id', values.sender_address_id || '')));

                    return parts.join(' · ');
                },
                fields: [
                    {name: 'carrier_code', label: $t('Carrier'), type: 'select'},
                    {
                        name: 'shipment_type',
                        label: $t('Shipment type'),
                        type: 'select',
                        filter: function (value, form) {
                            const c = carrier(form.find('[name=carrier_code]').val());

                            return !c || !c.shipment_types.length || c.shipment_types.indexOf(value) >= 0;
                        },
                        dependsOn: 'carrier_code'
                    },
                    {name: 'sender_address_id', label: $t('Sender'), type: 'select'},
                    {name: 'description', label: $t('Description'), type: 'text', maxlength: 30}
                ]
            },
            {
                title: $t('Pickup point'),
                visible: function () {
                    return carrier() && carrier().point !== 'NONE';
                },
                warning: function () {
                    return carrier() && carrier().point === 'REQUIRED' && !filled(values.recipient_point_id);
                },
                summary: function () {
                    if (filled(values.recipient_point_id)) {
                        return values.recipient_point_id;
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
                warning: state.codTooHigh,
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
                    {name: 'cod_amount', label: $t('Amount'), type: 'number', note: state.codNote},
                    {name: 'cod_type', label: $t('Collection type'), type: 'select'},
                    {name: 'cod_return_type', label: $t('Paid out'), type: 'select'},
                    {name: 'cod_bank_account_id', label: $t('Bank account'), type: 'select'}
                ]
            },
            {
                title: $t('Insurance and services'),
                summary: function () {
                    const parts = [];

                    if (carrier() && carrier().insurance) {
                        parts.push(filled(values.insurance_amount)
                            ? $t('Insured for %1').replace('%1', money(values.insurance_amount))
                            : $t('Not insured'));
                    }
                    values.services.forEach(function (service) {
                        parts.push(label('services', service));
                    });

                    return parts.length ? parts.join(' · ') : $t('None');
                },
                fields: [
                    {
                        name: 'insurance_amount',
                        label: $t('Insurance amount'),
                        type: 'number',
                        note: state.insuranceNote,
                        visible: function () {
                            return carrier() && carrier().insurance;
                        }
                    },
                    {
                        name: 'services',
                        label: $t('Additional services'),
                        type: 'checkboxes',
                        filter: function (value) {
                            return carrier() && carrier().services.indexOf(value) >= 0;
                        }
                    }
                ]
            },
            {
                title: $t('Handover'),
                warning: function () {
                    const c = carrier();

                    return Boolean(c && values.pickup_mode === 'courier' && (
                        (c.pickup_date_required && !filled(values.pickup_date))
                        || (c.pickup_time_required && !(filled(values.pickup_time_from) && filled(values.pickup_time_to)))
                    ));
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
                    {name: 'pickup_date', label: $t('Collection date'), type: 'date', visible: pickupDateVisible, shownFor: courierPickup},
                    {name: 'pickup_time_from', label: $t('From'), type: 'time', visible: pickupDateVisible, shownFor: courierPickup},
                    {name: 'pickup_time_to', label: $t('To'), type: 'time', visible: pickupDateVisible, shownFor: courierPickup}
                ]
            }
        ];
    };
});
