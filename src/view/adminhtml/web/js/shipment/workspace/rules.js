/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * What still stands in the way of placing the shipment, worked out from the form's data and what
 * the chosen carrier supports. The screen lists them under the sections, each with a way to fix
 * it. These are the usual refusals caught early; Polkurier still has the last word.
 */
define(['mage/translate'], function ($t) {
    'use strict';

    /**
     * @param {*} value
     * @returns {Boolean}
     */
    function filled(value) {
        return value !== null && value !== undefined && String(value).trim() !== '';
    }

    /**
     * @param {*} value
     * @returns {Number}
     */
    function amount(value) {
        const number = parseFloat(String(value === null || value === undefined ? '' : value).replace(',', '.'));

        return isNaN(number) ? 0 : number;
    }

    /**
     * @param {*} value
     * @param {String} currency
     * @returns {String}
     */
    function money(value, currency) {
        return amount(value).toLocaleString(document.documentElement.lang || undefined, {
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
    function itemsById(data) {
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
    function contentsWeight(pack, items) {
        return Math.round((pack.items || []).reduce(function (total, row) {
            const item = items[String(row.order_item_id)];

            return total + (item ? amount(item.weight) * amount(row.qty) : 0);
        }, 0) * 1000) / 1000;
    }

    /**
     * What a parcel weighs, as the parcel weight setting says: what's in it, or its template's
     * weight. A parcel with a product that has no weight takes its template's weight either way;
     * a parcel without a template weighs what's in it (ParcelWeightEnum::weigh).
     *
     * @param {String} mode 'products' or 'template'
     * @param {Number|null} templateWeight null for a parcel without a template
     * @param {Array<{weight: Number, qty: Number}>} contents a unit's weight and how many
     * @returns {Number} 0 when there's nothing to go by
     */
    function parcelWeight(mode, templateWeight, contents) {
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

        return mode !== 'template' && weighed ? weight : templateWeight;
    }

    /**
     * How much of each item the parcels hold, identical parcels counted each.
     *
     * @param {Array<Object>} packs
     * @returns {Object} quantities by order item id
     */
    function packed(packs) {
        const totals = {};

        (packs || []).forEach(function (pack) {
            (pack.items || []).forEach(function (row) {
                const id = String(row.order_item_id);

                totals[id] = (totals[id] || 0) + amount(row.qty) * Math.max(1, amount(pack.amount));
            });
        });

        return totals;
    }

    return {
        filled: filled,
        amount: amount,
        money: money,
        itemsById: itemsById,
        contentsWeight: contentsWeight,
        parcelWeight: parcelWeight,
        packed: packed,

        /**
         * @param {Object} data the form's data
         * @param {Object|null} quotes prices by carrier code, null until the first quote
         * @returns {Array<{card: String, text: String, blocking: Boolean}>}
         */
        issues: function (data, quotes) {
            const features = data.features || {},
                issues = [],
                cod = amount(data.cod_amount),
                courier = data.pickup_mode === 'courier',
                packs = (data.packs || []).filter(function (pack) {
                    return pack && !pack.delete;
                });

            function add(card, text, blocking) {
                issues.push({card: card, text: text, blocking: blocking !== false});
            }

            // Not blocking: Polkurier still checks the shipment when it's placed.
            if (filled(data.carriers_error)) {
                add('carrier', $t('The carrier list could not be read, so what the carrier supports isn\'t checked here: %1')
                    .replace('%1', data.carriers_error), false);
            }
            // A carrier that doesn't price the shipment is said under the price itself.
            if (!filled(data.carrier_code)) {
                add('carrier', $t('Choose a carrier.'));
            }
            if (features.point_required && !filled(data.recipient_point_id)) {
                add('recipient', $t('This carrier delivers to pickup points only: choose one.'));
            }
            if (!filled(data.recipient_phone)) {
                add('recipient', $t('The recipient needs a phone number.'));
            }
            if (courier && features.pickup_date_required && !filled(data.pickup_date)) {
                add('handover', $t('This carrier needs a collection date.'));
            }
            if (courier && features.pickup_time_required && !(filled(data.pickup_time_from) && filled(data.pickup_time_to))) {
                add('handover', $t('This carrier needs a collection time window.'));
            }
            if (data.pickup_mode === 'drop_off' && features.drop_off === false) {
                add('handover', $t('This carrier does not take parcels dropped off at a point.'));
            }
            if (cod > 0 && features.cod === false) {
                add('cod', $t('This carrier does not collect cash on delivery.'));
            }
            if (cod > 0 && filled(data.cod_remaining) && cod > amount(data.cod_remaining) + 0.005) {
                add('cod', amount(data.cod_collected) > 0
                    ? $t('The order\'s other Polkurier orders already collect %1: this one collects more than the %2 left.')
                        .replace('%1', money(data.cod_collected, data.currency))
                        .replace('%2', money(data.cod_remaining, data.currency))
                    : $t('Cash on delivery is more than the order total.'), false);
            }
            if (cod > 0 && data.cod_return_type === 'BA' && !filled(data.cod_bank_account_id)) {
                add('cod', $t('Choose the bank account to pay the cash on delivery out to.'));
            }
            if (cod > 0 && features.insurance_with_cod && amount(data.insurance_amount) <= 0) {
                add('extras', $t('This carrier insures every shipment with cash on delivery: enter the declared value.'));
            }
            (features.fields || []).forEach(function (field) {
                if (field.required && !filled((data.additional_fields || {})[field.name])) {
                    add('fields', $t('Fill in "%1".').replace('%1', field.label));
                }
            });
            if (data.can_ship === false && !data.replacing) {
                add('items', $t('Everything on the order is shipped, and each of its Magento shipments has its Polkurier order. To send a replacement, cancel the Polkurier order it replaces first.'));
            }
            if (data.can_ship !== false && (data.items || []).length) {
                const over = (data.items || []).filter(function (item) {
                    return amount(item.qty) < 0 || amount(item.qty) > amount(item.qty_to_ship) + 0.0001;
                });

                over.forEach(function (item) {
                    add('items', $t('You can ship at most %1 of "%2".')
                        .replace('%1', String(amount(item.qty_to_ship)))
                        .replace('%2', item.name));
                });
                if (!over.length && !(data.items || []).some(function (item) {
                    return amount(item.qty) > 0;
                })) {
                    add('items', $t('Choose at least one item to ship.'));
                }
            }
            if (data.can_ship !== false) {
                const items = itemsById(data),
                    totals = packed(packs);

                Object.keys(totals).forEach(function (id) {
                    if (items[id] && totals[id] > amount(items[id].qty) + 0.0001) {
                        add('parcels', $t('The parcels hold more of "%1" than the shipment carries.').replace('%1', items[id].name));
                    }
                });
                packs.forEach(function (pack, index) {
                    const weight = contentsWeight(pack, items),
                        template = (data.templates || []).find(function (candidate) {
                            return String(candidate.id) === String(pack.template_id || '');
                        });

                    // A warning only: the limit is the merchant's own, and the parcel may still go.
                    if (template && amount(template.weight) > 0 && amount(pack.weight) > amount(template.weight) + 0.0005) {
                        add('parcels', $t('Parcel %1 weighs %2 kg, more than %3 may weigh (%4 kg).')
                            .replace('%1', String(index + 1))
                            .replace('%2', String(amount(pack.weight)))
                            .replace('%3', template.name)
                            .replace('%4', String(amount(template.weight))), false);
                    }

                    if (weight > 0 && amount(pack.weight) > 0 && amount(pack.weight) < weight - 0.0005) {
                        add('parcels', $t('Parcel %1 weighs less than what it holds (%2 kg).')
                            .replace('%1', String(index + 1))
                            .replace('%2', String(weight)), false);
                    }
                });
            }
            if (!packs.length) {
                add('parcels', $t('Add a parcel.'));
            } else if (packs.some(function (pack) {
                return amount(pack.weight) <= 0 || amount(pack.length) <= 0 || amount(pack.width) <= 0 || amount(pack.height) <= 0;
            })) {
                add('parcels', $t('Enter the size and weight of every parcel.'));
            }

            return issues;
        }
    };
});
