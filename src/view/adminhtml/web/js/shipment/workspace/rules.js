/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * What still stands in the way of placing the shipment, worked out from the form's data and what
 * the chosen carrier supports. The screen lists them under the sections, each with a way to fix
 * it. These are the usual refusals caught early; Polkurier still has the last word.
 */
define(['mage/translate'], function ($t) {
    'use strict';

    // The fields a shipment draft is made of (Polkurier\Shipping\Model\Shipment\Draft\DraftHydrator),
    // out of everything the form's data holds.
    const DRAFT_FIELDS = [
        'order_id', 'carrier_code', 'shipment_type', 'description', 'sender_address_id', 'sender_point_id',
        'recipient_person', 'recipient_company', 'recipient_street', 'recipient_house_number',
        'recipient_flat_number', 'recipient_postcode', 'recipient_city', 'recipient_country',
        'recipient_phone', 'recipient_email', 'recipient_point_id', 'packs', 'cod_amount', 'cod_type',
        'cod_return_type', 'cod_bank_account_id', 'insurance_amount', 'cover_address_id', 'services',
        'pickup_mode', 'pickup_date', 'pickup_time_from', 'pickup_time_to', 'additional_fields',
        // A sender or bank account typed in rather than chosen, and whether it's kept.
        'new_sender_person', 'new_sender_company', 'new_sender_street', 'new_sender_house_number',
        'new_sender_flat_number', 'new_sender_postcode', 'new_sender_city', 'new_sender_country',
        'new_sender_phone', 'new_sender_email', 'keep_new_sender', 'new_sender_name',
        'new_bank_account_number', 'keep_new_bank_account', 'new_bank_account_name'
    ];

    /**
     * @param {Object} data the form's data
     * @returns {Array<String>} what a sender typed in still lacks, by name
     */
    function findMissingSenderFields(data) {
        // The sender fields Polkurier requires, with their names.
        const required = {
            new_sender_person: $t('name'),
            new_sender_street: $t('street'),
            new_sender_house_number: $t('house number'),
            new_sender_postcode: $t('postcode'),
            new_sender_city: $t('city'),
            new_sender_phone: $t('phone'),
            new_sender_email: $t('e-mail')
        };

        return Object.keys(required).filter(function (field) {
            return !isFilled(data[field]);
        }).map(function (field) {
            return required[field];
        });
    }

    /**
     * The drop-off point is where the parcels are dropped off: it counts only when they are, or
     * with a carrier that takes them only at points but not as dropped off.
     *
     * @param {String} requirement whether the carrier takes the parcels at a point: NONE, OPTIONAL or REQUIRED
     * @param {Boolean} dropOff whether the carrier takes parcels dropped off
     * @param {String} mode how the parcels get to the carrier
     * @returns {Boolean}
     */
    function isSenderPointUsed(requirement, dropOff, mode) {
        return (requirement || 'NONE') !== 'NONE' && (mode === 'drop_off' || requirement === 'REQUIRED');
    }

    /**
     * How the parcels are handed over unless chosen otherwise: dropped off for a carrier that takes
     * them only at points, or at the drop-off point when the carrier takes parcels at points;
     * otherwise a courier collects them.
     *
     * @param {Object} features the carrier's, from buildFeatures()
     * @param {String} senderPointId the drop-off point, '' without one
     * @returns {String} drop_off or courier
     */
    function getDefaultHandover(features, senderPointId) {
        const requirement = features.sender_point || 'NONE';

        return requirement === 'REQUIRED'
            || (requirement !== 'NONE' && features.drop_off !== false && Boolean(senderPointId))
            ? 'drop_off'
            : 'courier';
    }

    /**
     * Whether a way of handing the parcels over is offered for the carrier: a carrier that takes
     * parcels only at points has them dropped off, and a collective pickup needs a carrier that
     * books one.
     *
     * @param {String} mode courier, drop_off or multi
     * @param {Object} features the carrier's, from buildFeatures()
     * @param {Boolean|undefined} collective whether these parcels can go with a collective pickup
     * @returns {Boolean}
     */
    function isHandoverOffered(mode, features, collective) {
        if (features.sender_point === 'REQUIRED') {
            return mode === 'drop_off';
        }

        return (mode !== 'drop_off' || features.drop_off !== false) && (mode !== 'multi' || collective !== false);
    }

    /**
     * The draft taken from the form's data, with each item and parcel reduced to its own fields.
     * Everything else the screen shows (the carriers, the choices, the templates…) would be far
     * more than a request may carry.
     *
     * @param {Object} data the form's data
     * @param {Boolean} [withItems] whether to include the shipped items, which pricing doesn't need
     * @returns {Object}
     */
    function buildDraft(data, withItems) {
        const picked = {},
            item = function (row) {
                return {order_item_id: row.order_item_id, qty: row.qty};
            };

        DRAFT_FIELDS.forEach(function (field) {
            if (data[field] !== undefined) {
                picked[field] = data[field];
            }
        });
        picked.packs = (data.packs || []).filter(function (pack) {
            return pack && !pack.delete;
        }).map(function (pack) {
            return {
                template_id: pack.template_id,
                pack_type: pack.pack_type,
                length: pack.length,
                width: pack.width,
                height: pack.height,
                weight: pack.weight,
                amount: pack.amount,
                items: (pack.items || []).map(item),
                keep_as: pack.template_id ? '' : pack.keep_as || ''
            };
        });
        if (withItems) {
            picked.items = (data.items || []).map(item);
            picked.packs_estimated = data.packs_estimated;
            picked.target_shipment_id = data.target_shipment_id;
            picked.shipping_included = data.shipping_included ? 1 : 0;
            // Only where it's offered: a Magento shipment nobody was e-mailed about.
            picked.notify_customer = data.notify_offered && data.notify_customer ? 1 : 0;
        }

        return picked;
    }

    /**
     * @param {*} value
     * @returns {Boolean}
     */
    function isFilled(value) {
        return value !== null && value !== undefined && String(value).trim() !== '';
    }

    /**
     * The start of a text that fits in a number of bytes of UTF-8, without splitting a letter, as
     * Polkurier cuts the shipment description.
     *
     * @param {String} text
     * @param {Number} bytes
     * @returns {String}
     */
    function cutToBytes(text, bytes) {
        const encoder = new TextEncoder();
        let kept = '',
            used = 0;

        Array.from(String(text)).every(function (letter) {
            used += encoder.encode(letter).length;
            if (used > bytes) {
                return false;
            }
            kept += letter;

            return true;
        });

        return kept;
    }

    /**
     * @param {*} value
     * @returns {Number}
     */
    function parseAmount(value) {
        const number = parseFloat(String(value === null || value === undefined ? '' : value).replace(',', '.'));

        return isNaN(number) ? 0 : number;
    }

    /**
     * @param {*} value
     * @param {String} currency
     * @returns {String}
     */
    function formatMoney(value, currency) {
        return parseAmount(value).toLocaleString(document.documentElement.lang || undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + ' ' + (currency || 'PLN');
    }

    /**
     * The shipped items by order item id.
     *
     * @param {Object} data
     * @returns {Object}
     */
    function indexItemsById(data) {
        const items = {};

        (data.items || []).forEach(function (item) {
            items[String(item.order_item_id)] = item;
        });

        return items;
    }

    /**
     * What one parcel's items weigh, in kilograms.
     *
     * @param {Object} pack
     * @param {Object} items the shipped items by order item id
     * @returns {Number}
     */
    function calculateContentsWeight(pack, items) {
        return Math.round((pack.items || []).reduce(function (total, row) {
            const item = items[String(row.order_item_id)];

            return total + (item ? parseAmount(item.weight) * parseAmount(row.qty) : 0);
        }, 0) * 1000) / 1000;
    }

    /**
     * What a parcel weighs, as the parcel weight setting says: what's in it with its template's
     * tare, or its template's weight. A parcel with a product that has no weight takes its
     * template's weight either way; a parcel without a template weighs what's in it
     * (Polkurier\Shipping\Enum\Config\ParcelWeightEnum::weigh()).
     *
     * @param {String} mode 'products' or 'template'
     * @param {Number|null} templateWeight null for a parcel without a template
     * @param {Array<{weight: Number, qty: Number}>} contents a unit's weight and how many
     * @param {Number|null} [tare] what the template's packaging weighs
     * @returns {Number} 0 when there's nothing to go by
     */
    function calculateParcelWeight(mode, templateWeight, contents, tare) {
        const held = contents.filter(function (row) {
                return row.qty > 0;
            }),
            weight = Math.round(held.reduce(function (total, row) {
                return total + row.weight * row.qty;
            }, 0) * 1000) / 1000,
            weighed = held.length > 0 && held.every(function (row) {
                return row.weight > 0;
            });

        if (!(templateWeight > 0)) {
            return weight;
        }

        return mode !== 'template' && weighed
            ? Math.round((weight + Math.max(0, parseAmount(tare))) * 1000) / 1000
            : templateWeight;
    }

    /**
     * How much of each item the parcels hold, with each of several identical parcels counted.
     *
     * @param {Array<Object>} packs
     * @returns {Object} quantities by order item id
     */
    function countPacked(packs) {
        const totals = {};

        (packs || []).forEach(function (pack) {
            (pack.items || []).forEach(function (row) {
                const id = String(row.order_item_id);

                totals[id] = (totals[id] || 0) + parseAmount(row.qty) * Math.max(1, parseAmount(pack.amount));
            });
        });

        return totals;
    }

    /**
     * What the chosen quantities are worth, by the server's rule
     * (Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator::getShipmentWorth()): the
     * order total for the whole order, or when the products have no price; otherwise the products,
     * and the shipping and handling too when it's included (data.shipping_included).
     *
     * @param {Object} data the form's data
     * @returns {{products: Number, shipping: Number, order_total: Number, whole: Boolean, rest: Boolean,
     *     with_shipping: Boolean, value: Number, chosen: Boolean}} rest: whether it carries all the
     *     order still has to ship
     */
    function calculateShipmentWorth(data) {
        const total = parseAmount(data.grand_total),
            shipping = parseAmount(data.shipping_charged);
        let products = 0,
            // The list leaves out what's already shipped, so it's the whole order only when nothing is.
            whole = !(data.items || []).some(function (item) {
                return parseAmount(item.qty_shipped) > 0;
            }) && !data.partly_shipped,
            rest = true,
            chosen = false;

        if (data.replacing) {
            return Object.assign({rest: true}, data.replacing.worth);
        }
        (data.items || []).forEach(function (item) {
            const qty = parseAmount(item.qty);

            products += parseAmount(item.unit) * qty;
            whole = whole && qty >= parseAmount(item.qty_ordered);
            rest = rest && qty >= parseAmount(item.qty_to_ship) - 0.0001;
            chosen = chosen || qty > 0;
        });
        products = Math.round(products * 100) / 100;
        // The whole order includes the shipping and handling whatever was asked. A part includes it
        // when asked, and the part carrying the rest when no earlier part did; never twice.
        const covered = !whole && Boolean(data.shipping_covered),
            withShipping = whole || (!covered && (Boolean(data.shipping_included) || rest));

        return {
            products: products,
            shipping: shipping,
            order_total: total,
            whole: whole,
            rest: rest,
            shipping_covered: covered,
            with_shipping: withShipping,
            value: whole || products <= 0
                ? total
                : (withShipping ? Math.round((products + shipping) * 100) / 100 : products),
            chosen: chosen
        };
    }

    /**
     * What the shipment is insured for: what it collects or, without cash on delivery, what it's
     * worth. That way the parts' insurance adds up to the order total, as their cash on delivery does.
     *
     * @param {Number} cod
     * @param {Number} value the shipment value (calculateShipmentWorth)
     * @returns {Number}
     */
    function calculateInsurance(cod, value) {
        return cod > 0 ? cod : value;
    }

    /**
     * The services available for the shipment's type: those any carrier offers for it, so they
     * can be chosen before the carrier that offers them. Null when the carriers aren't known.
     *
     * @param {Object} data the form's data
     * @returns {Array<String>|null}
     */
    function findServices(data) {
        const carriers = data.carriers || {},
            type = data.shipment_type,
            // Offered with every carrier that takes pallets, for pallets only.
            pallet = type === 'palette' ? [] : data.pallet_services || [],
            services = [];

        if (!Object.keys(carriers).length) {
            return null;
        }
        Object.keys(carriers).forEach(function (code) {
            const carrier = carriers[code] || {},
                types = carrier.shipment_types || [];

            if (!isFilled(type) || !types.length || types.indexOf(type) !== -1) {
                (carrier.services || []).forEach(function (service) {
                    if (services.indexOf(service) === -1 && pallet.indexOf(service) === -1) {
                        services.push(service);
                    }
                });
            }
        });

        return services;
    }

    /**
     * The chosen services a carrier doesn't offer, counting the cover address as its service.
     *
     * @param {Object} data the form's data
     * @param {Array<String>|null|undefined} offered what the carrier offers, unknown when not given
     * @returns {Array<String>} their codes
     */
    function findLackingServices(data, offered) {
        const chosen = (data.services || []).slice();

        if (!offered) {
            return [];
        }
        if (isFilled(data.cover_address_id)) {
            chosen.push('COVER_ADDRESS_SENDER');
        }

        return chosen.filter(function (service) {
            return offered.indexOf(service) === -1;
        });
    }

    /**
     * What cash on delivery collects, worked out as
     * Polkurier\Shipping\Model\Shipment\Valuation\OrderAmountCalculator::getCodFor() does it.
     * Everything left to collect for the whole order, for the part carrying the rest, or when the
     * products have no price; otherwise the products, plus the shipping and handling when it's
     * included, but never more than is left.
     *
     * @param {Number} remaining what's left to collect on the order
     * @param {Object} worth as calculateShipmentWorth gives it
     * @returns {Number}
     */
    function calculateCod(remaining, worth) {
        if (worth.rest || worth.whole || worth.products <= 0) {
            return remaining;
        }

        return Math.min(
            remaining,
            Math.round((worth.products + (worth.with_shipping ? worth.shipping : 0)) * 100) / 100
        );
    }

    /**
     * The shipment types the parcels make, each listed once: "box", "envelope" or "palette". A
     * shipment that can be sent has one; without parcels, there are none.
     *
     * @param {Array<Object>} packs
     * @param {Object} kinds the shipment type of each parcel type
     * @returns {Array<String>}
     */
    function listShipmentTypes(packs, kinds) {
        return (packs || []).map(function (pack) {
            return (kinds || {})[pack.pack_type] || 'box';
        }).filter(function (type, index, types) {
            return types.indexOf(type) === index;
        });
    }

    /**
     * @param {String} type a shipment type
     * @returns {String} a message that the carrier doesn't take it
     */
    function describeTypeNotTaken(type) {
        return {
            envelope: $t('This carrier does not take envelopes.'),
            palette: $t('This carrier does not take pallets.')
        }[type] || $t('This carrier does not take parcels.');
    }

    /**
     * @param {Array<String>} types the shipment types of the parcels
     * @param {Array<String>} taken what the carrier takes, empty when not known
     * @returns {String} what's wrong with the parcels' kinds for the carrier, empty when nothing
     */
    function describeKindProblem(types, taken) {
        if (types.length > 1) {
            return $t('Parcels, envelopes and pallets can\'t go in one shipment: keep to one of them.');
        }

        return types.length && (taken || []).length && taken.indexOf(types[0]) < 0
            ? describeTypeNotTaken(types[0])
            : '';
    }

    /**
     * What a carrier supports, as the forms read it (data.features).
     *
     * @param {Object|null} carrier the carrier's entry in the carrier list; null when it isn't known
     * @returns {Object}
     */
    function buildFeatures(carrier) {
        const fields = carrier ? carrier.fields || [] : [];

        return {
            known: !!carrier,
            point_allowed: carrier ? carrier.point !== 'NONE' : true,
            point_required: carrier ? carrier.point === 'REQUIRED' : false,
            // Whether the parcels are handed over at a point: NONE, OPTIONAL or REQUIRED.
            sender_point: carrier ? carrier.sender_point || 'NONE' : 'NONE',
            // The account's drop-off point for the carrier, '' without one.
            default_sender_point: carrier ? carrier.default_sender_point || '' : '',
            providers: carrier ? carrier.providers : [],
            cod: carrier ? carrier.cod : true,
            insurance: carrier ? carrier.insurance : true,
            insurance_with_cod: carrier ? carrier.insurance_with_cod : false,
            services: carrier ? carrier.services : null,
            drop_off: carrier ? carrier.drop_off : true,
            pickup_date: carrier ? carrier.pickup_date : true,
            pickup_date_required: carrier ? carrier.pickup_date_required : false,
            pickup_time_required: carrier ? carrier.pickup_time_required : false,
            // Empty when not known: any.
            shipment_types: carrier ? carrier.shipment_types : [],
            fields: fields,
            has_fields: fields.length > 0
        };
    }

    /**
     * A pickup point belongs to the carrier it was chosen for: changing the carrier puts it aside,
     * and going back to that carrier brings it back.
     *
     * @param {Object} aside the points put aside, by carrier code; changed in place
     * @param {String} previous the carrier chosen so far
     * @param {String} code the carrier chosen now
     * @param {String} current the point chosen so far
     * @returns {String} the point that goes with the carrier chosen now
     */
    function swapPoint(aside, previous, code, current) {
        if (previous === code) {
            return current;
        }
        if (isFilled(previous)) {
            aside[previous] = current;
        }

        return aside[code] || '';
    }

    return {
        buildDraft: buildDraft,
        findMissingSenderFields: findMissingSenderFields,
        isSenderPointUsed: isSenderPointUsed,
        isHandoverOffered: isHandoverOffered,
        getDefaultHandover: getDefaultHandover,
        isFilled: isFilled,
        parseAmount: parseAmount,
        formatMoney: formatMoney,
        indexItemsById: indexItemsById,
        calculateContentsWeight: calculateContentsWeight,
        calculateParcelWeight: calculateParcelWeight,
        countPacked: countPacked,
        calculateShipmentWorth: calculateShipmentWorth,
        findServices: findServices,
        findLackingServices: findLackingServices,
        calculateCod: calculateCod,
        calculateInsurance: calculateInsurance,
        listShipmentTypes: listShipmentTypes,
        describeKindProblem: describeKindProblem,
        buildFeatures: buildFeatures,
        swapPoint: swapPoint,
        cutToBytes: cutToBytes,

        /**
         * @param {Object} data the form's data
         * @returns {Array<{card: String, text: String, blocking: Boolean, fix: (Object|Boolean|null), field: String}>}
         *     field: the form field the issue is about, for a page that has only some of them
         */
        findIssues: function (data) {
            const features = data.features || {},
                issues = [],
                cod = parseAmount(data.cod_amount),
                courier = data.pickup_mode === 'courier',
                packs = (data.packs || []).filter(function (pack) {
                    return pack && !pack.delete;
                });

            // field is the form field it's about. fix, when given, says where the problem is fixed
            // if that's not where the card's problems are: {parcel: index}, {part: name} of the
            // section, {modal: name} of the form, or false.
            function add(field, card, text, blocking, fix) {
                issues.push({
                    field: field,
                    card: card,
                    text: text,
                    blocking: blocking !== false,
                    fix: fix === undefined ? null : fix
                });
            }

            // Not blocking: Polkurier still checks the shipment when it's placed.
            if (isFilled(data.carriers_error)) {
                add(
                    'carrier_code',
                    'carrier',
                    $t('The carrier list could not be read, so what the carrier supports isn\'t checked here: %1')
                        .replace('%1', data.carriers_error),
                    false,
                    false
                );
            }
            // A carrier that doesn't price the shipment is reported under the price itself.
            if (!isFilled(data.carrier_code)) {
                add('carrier_code', 'carrier', $t('Choose a carrier.'));
            }
            // The account's carriers change: the order's carrier may have gone since, or been left
            // out of the carriers worked with.
            if (isFilled(data.carrier_code) && !isFilled(data.carriers_error) && Object.keys(data.carriers || {}).length
                && !(data.carriers || {})[data.carrier_code]
            ) {
                add(
                    'carrier_code',
                    'carrier',
                    $t('This carrier is no longer available: choose another.'),
                    true,
                    {modal: 'carrier_modal'}
                );
            }
            if (features.point_required && !isFilled(data.recipient_point_id)) {
                add(
                    'recipient_point_id',
                    'recipient',
                    $t('This carrier delivers to pickup points only: choose one.'),
                    true,
                    {part: 'point'}
                );
            }
            // Looked up when the screen opened: gone or closed since the order was placed.
            if (isFilled(data.recipient_point_id) && data.recipient_point_closed === data.recipient_point_id
                && features.point_allowed !== false
            ) {
                add(
                    'recipient_point_id',
                    'recipient',
                    $t('Pickup point %1 is closed or no longer exists: choose another.')
                        .replace('%1', data.recipient_point_id),
                    true,
                    {part: 'point'}
                );
            }
            if (features.sender_point === 'REQUIRED'
                && isSenderPointUsed(features.sender_point, features.drop_off, data.pickup_mode)
                && !isFilled(data.sender_point_id)
            ) {
                add(
                    'sender_point_id',
                    'handover',
                    $t('This carrier takes the parcels at a point: choose the drop-off point you hand them over at.'),
                    true,
                    {part: 'handover'}
                );
            }
            if (data.sender_address_id === 'new' && findMissingSenderFields(data).length) {
                add(
                    'sender_address_id',
                    'sender',
                    $t('Fill in the sender\'s %1.').replace('%1', findMissingSenderFields(data).join(', '))
                );
            }
            // Not blocking: the description is cut when the shipment is placed.
            if (data.description_max_length
                && cutToBytes(data.description || '', data.description_max_length) !== String(data.description || '')
            ) {
                add(
                    'description',
                    'description',
                    $t('Only the start of the description fits on the label: "%1".')
                        .replace('%1', cutToBytes(data.description, data.description_max_length)),
                    false
                );
            }
            if (!isFilled(data.recipient_phone)) {
                add('recipient_phone', 'recipient', $t('The recipient needs a phone number.'));
            }
            if (courier && features.pickup_date_required && !isFilled(data.pickup_date)) {
                add('pickup_date', 'handover', $t('This carrier needs a collection date.'));
            }
            if (courier && features.pickup_time_required
                && !(isFilled(data.pickup_time_from) && isFilled(data.pickup_time_to))
            ) {
                add('pickup_time_from', 'handover', $t('This carrier needs a collection time window.'));
            }
            // A carrier that takes parcels only at points has them dropped off, whatever else it says.
            if (data.pickup_mode === 'drop_off' && features.drop_off === false
                && features.sender_point !== 'REQUIRED'
            ) {
                add('pickup_mode', 'handover', $t('This carrier does not take parcels dropped off at a point.'));
            }
            if (cod > 0 && features.cod === false) {
                add(
                    'cod_amount',
                    'cod',
                    $t('This carrier does not collect cash on delivery.'),
                    true,
                    {modal: 'carrier_modal'}
                );
            }
            if (parseAmount(data.insurance_amount) > 0 && features.insurance === false) {
                add(
                    'insurance_amount',
                    'extras',
                    $t('This carrier does not insure shipments.'),
                    true,
                    {modal: 'carrier_modal'}
                );
            }
            // Chosen before the carrier, since another carrier offers them.
            findLackingServices(data, features.services).forEach(function (service) {
                add(
                    'services',
                    'carrier',
                    $t('This carrier does not offer "%1".').replace('%1', service === 'COVER_ADDRESS_SENDER'
                        ? $t('Another sender on the label')
                        : ((data.choices || {}).services || {})[service] || service),
                    true,
                    {modal: 'carrier_modal'}
                );
            });
            if (cod > 0 && isFilled(data.cod_remaining) && cod > parseAmount(data.cod_remaining) + 0.005) {
                add('cod_amount', 'cod', parseAmount(data.cod_collected) > 0
                    ? $t('The order\'s other Polkurier orders already collect %1: this one collects more than the %2 left.')
                        .replace('%1', formatMoney(data.cod_collected, data.currency))
                        .replace('%2', formatMoney(data.cod_remaining, data.currency))
                    : $t('Cash on delivery is more than the order total.'), false);
            }
            if (cod > 0 && data.cod_return_type === 'BA' && !isFilled(data.cod_bank_account_id)) {
                add('cod_bank_account_id', 'cod', $t('Choose the bank account to pay the cash on delivery out to.'));
            }
            if (cod > 0 && data.cod_return_type === 'BA' && data.cod_bank_account_id === 'new'
                && !isFilled(data.new_bank_account_number)
            ) {
                add(
                    'new_bank_account_number',
                    'cod',
                    $t('Enter the number of the bank account the cash on delivery is paid out to.')
                );
            }
            if (cod > 0 && features.insurance_with_cod && parseAmount(data.insurance_amount) <= 0) {
                add(
                    'insurance_amount',
                    'extras',
                    $t('This carrier insures every shipment with cash on delivery: enter the declared value.')
                );
            }
            (features.fields || []).forEach(function (field) {
                if (field.required && !isFilled((data.additional_fields || {})[field.name])) {
                    add('additional_fields', 'fields', $t('Fill in "%1".').replace('%1', field.label));
                }
            });
            if (data.can_ship === false && !data.replacing) {
                add('items', 'items', $t('Everything on the order is shipped, and each of its Magento shipments has its Polkurier order. To send a replacement, cancel the Polkurier order it replaces first.'));
            }
            if (data.can_ship !== false && (data.items || []).length) {
                const over = (data.items || []).filter(function (item) {
                    return parseAmount(item.qty) < 0 || parseAmount(item.qty) > parseAmount(item.qty_to_ship) + 0.0001;
                });

                over.forEach(function (item) {
                    add('items', 'items', $t('You can ship at most %1 of "%2".')
                        .replace('%1', String(parseAmount(item.qty_to_ship)))
                        .replace('%2', item.name));
                });
                if (!over.length && !(data.items || []).some(function (item) {
                    return parseAmount(item.qty) > 0;
                })) {
                    add('items', 'items', $t('Choose at least one item to ship.'));
                }
            }
            if (data.can_ship !== false) {
                const items = indexItemsById(data),
                    totals = countPacked(packs);

                Object.keys(totals).forEach(function (id) {
                    if (items[id] && totals[id] > parseAmount(items[id].qty) + 0.0001) {
                        add(
                            'packs',
                            'parcels',
                            $t('The parcels hold more of "%1" than the shipment carries.')
                                .replace('%1', items[id].name),
                            true,
                            {
                                parcel: packs.findIndex(function (pack) {
                                    return (pack.items || []).some(function (row) {
                                        return String(row.order_item_id) === id && parseAmount(row.qty) > 0;
                                    });
                                })
                            }
                        );
                    }
                });
                packs.forEach(function (pack, index) {
                    const weight = calculateContentsWeight(pack, items),
                        template = (data.templates || []).find(function (candidate) {
                            return String(candidate.id) === String(pack.template_id || '');
                        });

                    // A warning only: the limit is the merchant's own, and the parcel may still go.
                    if (template && parseAmount(template.weight) > 0
                        && parseAmount(pack.weight) > parseAmount(template.weight) + 0.0005
                    ) {
                        add('packs', 'parcels', $t('Parcel %1 weighs %2 kg, more than %3 may weigh (%4 kg).')
                            .replace('%1', String(index + 1))
                            .replace('%2', String(parseAmount(pack.weight)))
                            .replace('%3', template.name)
                            .replace('%4', String(parseAmount(template.weight))), false, {parcel: index});
                    }

                    if (weight > 0 && parseAmount(pack.weight) > 0 && parseAmount(pack.weight) < weight - 0.0005) {
                        add('packs', 'parcels', $t('Parcel %1 weighs less than what it holds (%2 kg).')
                            .replace('%1', String(index + 1))
                            .replace('%2', String(weight)), false, {parcel: index});
                    }
                });
            }
            const types = listShipmentTypes(packs, (data.choices || {}).pack_shipment_type);

            if (types.length > 1) {
                add('packs', 'parcels', describeKindProblem(types, []));
            } else if (describeKindProblem(types, features.shipment_types)) {
                add('packs', 'carrier', describeKindProblem(types, features.shipment_types));
            }
            const incomplete = packs.findIndex(function (pack) {
                return parseAmount(pack.weight) <= 0 || parseAmount(pack.length) <= 0
                    || parseAmount(pack.width) <= 0 || parseAmount(pack.height) <= 0;
            });

            if (!packs.length) {
                add('packs', 'parcels', $t('Add a parcel.'));
            } else if (incomplete >= 0) {
                add('packs', 'parcels', $t('Enter the size and weight of every parcel.'), true, {parcel: incomplete});
            }

            return issues;
        }
    };
});
