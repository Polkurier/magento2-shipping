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
            filled = rules.filled,
            // Points put aside by changing the carrier, by the carrier they were chosen for.
            pointsByCarrier = {};

        let insuranceEdited = false,
            codEdited = false;

        values.services = (values.services || []).filter(Boolean);

        const state = {
            ITEM_INPUT: ITEM_INPUT,
            values: values,
            filled: filled,

            /**
             * @param {String} service
             * @returns {Boolean} whether any carrier offers it, so it can be chosen before the carrier
             */
            anyCarrierOffers: function (service) {
                return Object.keys(config.carriers || {}).some(function (code) {
                    return (config.carriers[code].services || []).indexOf(service) !== -1;
                });
            },

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
                const worth = state.shipmentWorth();

                return worth === null ? null : worth.value;
            },

            /**
             * The shipment value with what it comes from, as OrderAmounts::getShipmentWorth gives it.
             *
             * @returns {{products: Number, whole: Boolean, rest: Boolean, value: Number}|null} null
             *     when the page has no quantities to go by; rest: whether it carries all the order
             *     still has to ship
             */
            shipmentWorth: function () {
                const items = config.items || {},
                    qtys = {};
                let value = 0,
                    whole = true,
                    rest = true;

                // A shipment there is carries what it does; a new one what its form says.
                Object.keys(config.shippedQtys || {}).forEach(function (id) {
                    qtys[id] = parseFloat(config.shippedQtys[id]) || 0;
                });
                $(config.existingShipment ? [] : ITEM_INPUT).each(function () {
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
                    rest = rest && qty >= items[id].qty_to_ship - 0.0001;
                });

                value = Math.round(value * 100) / 100;
                // A part goes without the shipping and handling here, but the part carrying the rest,
                // which collects it, when no earlier part included it; only the Polkurier shipment
                // screen asks to include it with another part.
                const shipping = Number(config.shippingCharged) || 0,
                    withShipping = whole || (rest && !config.shippingCovered);

                return {
                    products: value,
                    shipping: shipping,
                    whole: whole,
                    rest: rest,
                    with_shipping: withShipping,
                    value: whole || value <= 0
                        ? Number(config.grandTotal)
                        : (withShipping ? Math.round((value + shipping) * 100) / 100 : value)
                };
            },

            /**
             * Keeps the cash on delivery at what the shipment collects (rules.codFor), unless it was
             * set by hand or is off. A shipment there is carries what it does already.
             *
             * @returns {Boolean} whether it changed
             */
            followCod: function () {
                const c = state.carrier(),
                    worth = config.existingShipment ? null : state.shipmentWorth();
                let cod;

                if (codEdited || worth === null || !c || !c.cod || !(Number(values.cod_amount) > 0)) {
                    return false;
                }
                cod = (Math.round(rules.codFor(Number(config.codRemaining), worth) * 100) / 100).toFixed(2);
                if (cod === Number(values.cod_amount).toFixed(2)) {
                    return false;
                }
                values.cod_amount = cod;

                return true;
            },

            /**
             * The merchant set the cash on delivery: it no longer follows the quantities.
             */
            codSetByHand: function () {
                codEdited = true;
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
                values.insurance_amount = rules.insuranceFor(Number(values.cod_amount) || 0, value).toFixed(2);

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
             * Whether the carrier wants the shipment insured for the cash on delivery, and it isn't.
             *
             * @returns {Boolean}
             */
            insuranceTooLow: function () {
                const c = state.carrier();

                return Boolean(c && c.insurance && c.insurance_with_cod && filled(values.cod_amount)
                    && !(Number(values.insurance_amount) >= Number(values.cod_amount) - 0.005));
            },

            /**
             * What keeps the label from being created: what Polkurier would refuse, or what the
             * carrier needs and isn't filled in.
             *
             * @returns {String[]}
             */
            problems: function () {
                const c = state.carrier(),
                    problems = [];

                if (!filled(values.carrier_code)) {
                    return [$t('Choose a carrier.')];
                }
                // The account's carriers change: the order's may have gone since.
                if (!c) {
                    return [$t('This carrier is no longer available: choose another.')];
                }
                if (c.sender_point === 'REQUIRED' && !filled(values.sender_point_id)) {
                    problems.push($t('This carrier takes the parcels at a point: choose the drop-off point you hand them over at.'));
                }
                if (values.sender_address_id === 'new' && rules.newSenderLacking(values).length) {
                    problems.push($t('Fill in the sender\'s %1.').replace('%1', rules.newSenderLacking(values).join(', ')));
                }
                if (filled(values.cod_amount) && values.cod_return_type === 'BA' && values.cod_bank_account_id === 'new'
                    && !filled(values.new_bank_account_number)
                ) {
                    problems.push($t('Enter the number of the bank account the cash on delivery is paid out to.'));
                }
                if (c.point === 'REQUIRED' && !filled(values.recipient_point_id)) {
                    problems.push($t('This carrier delivers to pickup points only: choose one.'));
                }
                rules.servicesLacking(values, c.services).forEach(function (service) {
                    problems.push($t('This carrier does not offer "%1".').replace('%1', service === 'COVER_ADDRESS_SENDER'
                        ? $t('Another sender on the label')
                        : state.optionLabel('services', service) || service));
                });
                if (state.codTooHigh()) {
                    problems.push($t('Cash on delivery is more than the %1 left to collect.').replace('%1', state.money(config.codRemaining)));
                }
                if (state.insuranceTooLow()) {
                    problems.push($t('With cash on delivery, this carrier needs the shipment insured for at least %1.')
                        .replace('%1', state.money(values.cod_amount)));
                }
                if (values.pickup_mode === 'courier' && (
                    (c.pickup_date_required && !filled(values.pickup_date))
                    || (c.pickup_time_required && !(filled(values.pickup_time_from) && filled(values.pickup_time_to)))
                )) {
                    problems.push($t('Choose when the courier collects the parcels.'));
                }

                return problems;
            },

            /**
             * What stands in the way of the packages going: parcels of more than one kind, a kind
             * the carrier doesn't take, or services for pallets with other packages. Magento's packaging window has them, so it's checked
             * when the label is asked for.
             *
             * @param {Array<String>} containers the parcel type of each package
             * @returns {Array<String>}
             */
            packageProblems: function (containers) {
                const c = state.carrier(),
                    types = rules.shipmentTypes(containers.map(function (container) {
                        return {pack_type: container};
                    }), config.packShipmentTypes),
                    problem = rules.kindProblem(types, c ? c.shipment_types : []),
                    pallet = values.services.filter(function (service) {
                        return (config.palletServices || []).indexOf(service) !== -1;
                    }),
                    problems = problem ? [problem] : [];

                if (pallet.length && types.length === 1 && types[0] !== 'palette') {
                    problems.push($t('These services are for pallets only: %1.').replace('%1', pallet.map(function (service) {
                        return state.optionLabel('services', service) || service;
                    }).join(', ')));
                }

                return problems;
            },

            /**
             * A pickup point belongs to the carrier it was chosen for: changing the carrier puts it
             * aside, and going back to that carrier brings it back.
             *
             * @param {String} previous
             * @param {String} code
             */
            swapPoint: function (previous, code) {
                if (previous === code) {
                    return;
                }
                if (filled(previous)) {
                    pointsByCarrier[previous] = values.recipient_point_id || '';
                }
                values.recipient_point_id = pointsByCarrier[code] || '';
            },

            /**
             * Drops what the chosen carrier does not support, and adds insurance where the carrier
             * insists on it with cash on delivery.
             */
            /**
             * @returns {String} the drop-off point of the chosen sender address, '' without one
             */
            senderAddressPoint: function () {
                const address = (config.senderPoints || {})[String(values.sender_address_id || '')];

                return address && address.point_id ? address.point_id : '';
            },

            /**
             * @returns {String} the chosen sender's postcode, where the drop-off point's map starts
             */
            senderPostcode: function () {
                if (values.sender_address_id === 'new') {
                    return values.new_sender_postcode || '';
                }
                const address = (config.senderPoints || {})[String(values.sender_address_id || '')];

                return address ? address.postcode : '';
            },

            normalize: function () {
                const c = state.carrier();

                if (!c) {
                    return;
                }
                // Services stay: chosen first, they're matched with a carrier (problems()). The
                // cover sender address comes with the cover address, not as a service.
                values.services = values.services.filter(function (service) {
                    return service !== 'COVER_ADDRESS_SENDER';
                });
                if (c.point === 'NONE') {
                    values.recipient_point_id = '';
                }
                if ((c.sender_point || 'NONE') === 'NONE') {
                    values.sender_point_id = '';
                }
                if (!c.cod) {
                    values.cod_amount = '';
                }
                if (!c.insurance) {
                    values.insurance_amount = '';
                } else if (c.insurance_with_cod && filled(values.cod_amount) && !filled(values.insurance_amount)) {
                    values.insurance_amount = Number(values.cod_amount).toFixed(2);
                }
                if (values.pickup_mode === 'drop_off' && !c.drop_off) {
                    values.pickup_mode = 'courier';
                }
                // Taken only at points: dropped off there.
                if (c.sender_point === 'REQUIRED' && c.drop_off) {
                    values.pickup_mode = 'drop_off';
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
                const worth = state.shipmentWorth();

                if (worth === null) {
                    return $t('Leave empty for none.');
                }

                return (worth.whole || worth.products <= 0
                    ? $t('Leave empty for none. Shipment value: %1, the order total, as all of the order ships; it includes the %3 the customer was charged for shipping and handling.')
                    : (worth.with_shipping
                        ? $t('Leave empty for none. Shipment value: %1, the products with tax and after discounts and the shipping and handling, as this part carries the rest of the order (order total: %2, of which shipping and handling: %3).')
                        : $t('Leave empty for none. Shipment value: %1, the products with tax and after discounts, as only part of the order ships (order total: %2, of which shipping and handling: %3).')))
                    .replace('%1', state.money(worth.value))
                    .replace('%2', state.money(config.grandTotal))
                    .replace('%3', state.money(config.shippingCharged));
            }
        };

        return state;
    };
});
