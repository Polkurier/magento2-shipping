/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * What the shipment carries in Magento: items the order has left, with the quantity of each, so an
 * order can go out in parts. With nothing left, it replaces a cancelled Polkurier order on that
 * order's Magento shipment. The insurance follows what the shipment is worth, until it's set by hand.
 */
define([
    'ko',
    'mage/translate',
    './part',
    './rules'
], function (ko, $t, Part, rules) {
    'use strict';

    /**
     * @param {*} value
     * @returns {String}
     */
    function quantity(value) {
        return String(rules.amount(value));
    }

    return Part.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/workspace/items'
        },

        initState: function () {
            this.rows = ko.observableArray([]);
            this.canShip = ko.observable(true);
            this.replacingText = ko.observable('');
            this.valueText = ko.observable('');
            // The insurance this part last set, or started with: anything else was set by hand.
            this.followed = null;
        },

        refresh: function () {
            const data = this.get() || {},
                items = data.items || [];

            this.canShip(data.can_ship !== false && items.length > 0);
            this.replacingText(this.describeReplacing(data.replacing));
            if (this.rows().length !== items.length) {
                this.rows(items.map(this.makeRow, this));
            }
            items.forEach(function (item, index) {
                const row = this.rows()[index];

                if (rules.amount(row.qty()) !== rules.amount(item.qty)) {
                    row.syncing = true;
                    row.qty(quantity(item.qty));
                    row.syncing = false;
                }
            }, this);
            this.valueText($t('This shipment is worth %1.').replace('%1', this.money(data.shipment_value)));
            if (this.followed === null) {
                this.followed = rules.amount(data.insurance_amount);
            }
        },

        /**
         * @param {Object|null} replacing the Magento shipment the Polkurier order goes on
         * @returns {String}
         */
        describeReplacing: function (replacing) {
            if (!replacing) {
                return '';
            }

            return (replacing.replaces
                ? $t('Everything on the order is shipped. This Polkurier order replaces cancelled order %2 on Magento shipment #%1, and its waybills replace the tracking numbers there.')
                : $t('Everything on the order is shipped. This Polkurier order goes on Magento shipment #%1, which has none, and its waybills become its tracking numbers.'))
                .replace('%1', replacing.increment_id)
                .replace('%2', replacing.replaces || '');
        },

        /**
         * @param {Object} item
         * @param {Number} index
         * @returns {Object}
         */
        makeRow: function (item, index) {
            const row = {
                name: item.name,
                sku: item.sku,
                ordered: quantity(item.qty_ordered),
                shipped: quantity(item.qty_shipped),
                left: rules.amount(item.qty_to_ship),
                leftText: $t('of %1 left').replace('%1', quantity(item.qty_to_ship)),
                label: $t('Quantity of %1 to ship').replace('%1', item.name),
                qty: ko.observable(quantity(item.qty)),
                syncing: false
            };

            row.invalid = ko.pureComputed(function () {
                const qty = rules.amount(row.qty());

                return qty < 0 || qty > row.left + 0.0001;
            });
            row.qty.subscribe(function (value) {
                if (!row.syncing) {
                    this.set('items.' + index + '.qty', value);
                    this.follow();
                }
            }, this);

            return row;
        },

        shipAll: function () {
            this.rows().forEach(function (row) {
                row.qty(quantity(row.left));
            });
        },

        /**
         * What the chosen quantities are worth, by the server's rule: the order total for the whole
         * order, or when nothing matches.
         *
         * @param {Object} data
         * @returns {Number}
         */
        value: function (data) {
            let value = 0,
                whole = true;

            if (data.replacing) {
                return rules.amount(data.replacing.shipment_value);
            }
            (data.items || []).forEach(function (item) {
                const qty = rules.amount(item.qty);

                value += rules.amount(item.unit) * qty;
                whole = whole && qty >= rules.amount(item.qty_ordered);
            });

            return whole || value <= 0 ? rules.amount(data.grand_total) : Math.round(value * 100) / 100;
        },

        /**
         * Keeps the insurance at what the shipment is worth, unless it's off or was set by hand.
         */
        follow: function () {
            const data = this.get(),
                value = this.value(data),
                before = rules.amount(data.shipment_value),
                insurance = rules.amount(data.insurance_amount),
                cod = rules.amount(data.cod_amount),
                // What this part set, or what the insurance switch fills in: not set by hand.
                automatic = [this.followed, before, Math.max(before, cod)];
            let next = value;

            this.set('shipment_value', value);
            if (insurance <= 0 || !automatic.some(function (amount) {
                return Math.abs(insurance - amount) < 0.005;
            })) {
                return;
            }
            if (this.features().insurance_with_cod && cod > 0) {
                next = Math.max(value, cod);
            }
            this.followed = next;
            this.set('insurance_amount', next.toFixed(2));
        }
    });
});
