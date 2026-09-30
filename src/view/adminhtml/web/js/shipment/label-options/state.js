/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The label options chosen so far and the rules they follow: what the chosen carrier supports,
 * what the shipment is worth, and how cash on delivery and insurance follow from it.
 */
define([
    'jquery',
    'mage/translate',
    'Polkurier_Shipping/js/shipment/workspace/rules'
], function ($, $t, rules) {
    'use strict';

    const ITEM_INPUT = 'input[name^="shipment[items]["]';

    /**
     * @param {Object} config the page's label options, as LabelOptions gives them
     * @returns {Object}
     */
    return function (config) {
        const values = $.extend({services: []}, config.values),
            filled = rules.filled;

        let insuranceEdited = false;

        values.services = (values.services || []).filter(Boolean);

        const state = {
            ITEM_INPUT: ITEM_INPUT,
            values: values,
            filled: filled,

            /**
             * @param {String} [code] the chosen carrier when left out
             * @returns {Object|null} what the carrier supports
             */
            carrier: function (code) {
                return config.carriers[code === undefined ? values.carrier_code : code] || null;
            },

            optionLabel: function (field, value) {
                const option = (config.options[field] || []).find(function (item) {
                    return String(item.value) === String(value);
                });

                return option ? option.label : '';
            },

            money: function (amount) {
                return rules.money(amount, config.currency);
            },

            /**
             * What the quantities on the page are worth: the order total when they cover the whole
             * order, as on the server.
             *
             * @returns {Number|null} null when the page has no quantities to go by
             */
            shipmentValue: function () {
                const items = config.items || {},
                    qtys = {};
                let value = 0,
                    whole = true;

                $(ITEM_INPUT).each(function () {
                    const match = /^shipment\[items]\[(\d+)]$/.exec(this.name);

                    // A hidden 0 comes before the checkbox or field of the same item: the last one counts.
                    if (match && !this.disabled && (this.type !== 'checkbox' || this.checked)) {
                        qtys[match[1]] = parseFloat(this.value) || 0;
                    }
                });
                if (!Object.keys(qtys).length) {
                    return null;
                }
                Object.keys(items).forEach(function (id) {
                    const qty = qtys[id] || 0;

                    value += items[id].unit * qty;
                    whole = whole && qty >= items[id].qty;
                });

                return whole || value <= 0 ? config.grandTotal : Math.round(value * 100) / 100;
            },

            /**
             * Keeps the insurance at what the shipment is worth, unless it was set by hand or is off.
             *
             * @returns {Boolean} whether it changed
             */
            followInsurance: function () {
                const c = state.carrier(),
                    value = state.shipmentValue();

                if (insuranceEdited || value === null || !c || !c.insurance || !filled(values.insurance_amount)) {
                    return false;
                }
                values.insurance_amount = (c.insurance_with_cod && filled(values.cod_amount)
                    ? Math.max(value, Number(values.cod_amount))
                    : value).toFixed(2);

                return true;
            },

            /**
             * The merchant set the insurance: it no longer follows the quantities.
             */
            insuranceSetByHand: function () {
                insuranceEdited = true;
            },

            codTooHigh: function () {
                return filled(values.cod_amount) && Number(values.cod_amount) > Number(config.codRemaining) + 0.005;
            },

            /**
             * Drops what the chosen carrier does not support, and adds insurance where the carrier
             * insists on it with cash on delivery.
             */
            normalize: function () {
                const c = state.carrier();

                if (!c) {
                    return;
                }
                if (c.shipment_types.length && c.shipment_types.indexOf(values.shipment_type) < 0) {
                    values.shipment_type = c.shipment_types[0];
                }
                values.services = values.services.filter(function (service) {
                    return c.services.indexOf(service) >= 0;
                });
                if (c.point === 'NONE') {
                    values.recipient_point_id = '';
                }
                if (!c.cod) {
                    values.cod_amount = '';
                }
                if (!c.insurance) {
                    values.insurance_amount = '';
                } else if (c.insurance_with_cod && filled(values.cod_amount) && !filled(values.insurance_amount)) {
                    values.insurance_amount = values.cod_amount;
                }
                if (values.pickup_mode === 'drop_off' && !c.drop_off) {
                    values.pickup_mode = 'courier';
                }
                if (!c.pickup_date) {
                    values.pickup_date = values.pickup_time_from = values.pickup_time_to = '';
                }
            },

            codNote: function () {
                if (Number(config.codCollected) > 0) {
                    return $t('Leave empty for none. Order total: %1, of which the order\'s other Polkurier orders collect %2.')
                        .replace('%1', state.money(config.grandTotal))
                        .replace('%2', state.money(config.codCollected));
                }

                return $t('Leave empty for none. Order total: %1').replace('%1', state.money(config.grandTotal));
            },

            insuranceNote: function () {
                const value = state.shipmentValue();

                return value === null
                    ? $t('Leave empty for none.')
                    : $t('Leave empty for none. This shipment is worth %1.').replace('%1', state.money(value));
            }
        };

        return state;
    };
});
