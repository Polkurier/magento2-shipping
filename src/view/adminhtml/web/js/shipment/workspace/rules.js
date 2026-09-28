/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * What still stands in the way of placing the shipment, worked out from the form's data and what
 * the chosen carrier supports. The cards show the issues that are theirs, the summary lists them
 * all. These are the usual refusals caught early; Polkurier still has the last word.
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

    return {
        filled: filled,
        amount: amount,

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

            if (!filled(data.carrier_code)) {
                add('carrier', $t('Choose a carrier.'));
            } else if (quotes && !quotes[data.carrier_code]) {
                add('carrier', $t('This carrier does not price the shipment as it is, so Polkurier will probably refuse it.'), false);
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
