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
     * @param {Object} config the page's label options, as
     *     Polkurier\Shipping\ViewModel\Adminhtml\LabelOptions gives them
     * @returns {Object}
     */
    return function (config) {
        const values = $.extend({services: []}, config.values),
            filled = rules.filled,
            // Points set aside when the carrier changed, keyed by the carrier they were chosen for.
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
             * The shipment value with what it comes from (rules.shipmentWorth), for the quantities on
             * the page.
             *
             * @returns {Object|null} null when the page has no quantities to go by
             */
            shipmentWorth: function () {
                const items = config.items || {},
                    shipped = config.shippedQtys || {},
                    qtys = {};

                // An existing shipment carries what it already does; a new one, what its form says.
                Object.keys(shipped).forEach(function (id) {
                    qtys[id] = parseFloat(shipped[id]) || 0;
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

                // Only the Polkurier shipment screen asks to include the shipping and handling with
                // a part that doesn't carry the rest.
                return rules.shipmentWorth({
                    grand_total: config.grandTotal,
                    shipping_charged: config.shippingCharged,
                    shipping_covered: config.shippingCovered,
                    items: Object.keys(items).map(function (id) {
                        return {
                            unit: items[id].unit,
                            qty: qtys[id] || 0,
                            qty_ordered: items[id].qty,
                            // An existing shipment counts among the shipped; take it out to get
                            // what went before it.
                            qty_shipped: items[id].qty_shipped - (parseFloat(shipped[id]) || 0),
                            qty_to_ship: items[id].qty_to_ship
                        };
                    })
                });
            },

            /**
             * Keeps the cash on delivery at what the shipment collects (rules.codFor), unless it was
             * set by hand or is off. An existing shipment keeps the amount it already has.
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
             * What the shipment screen's rules find wrong with the options, limited to the given
             * fields, since the page has only some of the screen's fields.
             *
             * @param {Array<String>} [fields] the fields to report on; the page's when left out
             * @param {Boolean} [blockingOnly]
             * @returns {Array<String>}
             */
            issues: function (fields, blockingOnly) {
                const services = {},
                    names = fields || config.fields;

                (config.options.services || []).forEach(function (option) {
                    services[option.value] = option.label;
                });

                return rules.issues($.extend({}, values, {
                    features: rules.featuresOf(state.carrier()),
                    carriers: config.carriers,
                    carriers_error: config.carriersError,
                    choices: {services: services},
                    currency: config.currency,
                    cod_collected: config.codCollected,
                    cod_remaining: config.codRemaining,
                    recipient_point_closed: config.point && config.point.closed ? config.point.id : ''
                })).filter(function (issue) {
                    return names.indexOf(issue.field) !== -1 && (!blockingOnly || issue.blocking);
                }).map(function (issue) {
                    return issue.text;
                });
            },

            /**
             * What keeps the label from being created: what Polkurier would refuse, or what the
             * carrier needs and isn't filled in.
             *
             * @returns {String[]}
             */
            problems: function () {
                return state.issues(null, true);
            },

            /**
             * Why the packages can't be sent: parcels of more than one kind, a kind the carrier
             * doesn't take, or pallet services with other packages. The packages are in Magento's
             * packaging window, so this is checked when the label is asked for.
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
                    problems.push(
                        $t('These services are for pallets only: %1.')
                            .replace('%1', pallet.map(function (service) {
                                return state.optionLabel('services', service) || service;
                            }).join(', '))
                    );
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
                values.recipient_point_id = rules.swapPoint(pointsByCarrier, previous, code, {
                    id: values.recipient_point_id || '',
                    label: ''
                }).id;
            },

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

            /**
             * Drops what the chosen carrier does not support, and adds insurance where the carrier
             * insists on it with cash on delivery.
             */
            normalize: function () {
                const c = state.carrier();

                if (!c) {
                    return;
                }
                // Services stay: they can be chosen before the carrier and are checked against it
                // (problems()). COVER_ADDRESS_SENDER comes with the cover address, not as a service.
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
                // A carrier that takes parcels only at points: they're dropped off there.
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
