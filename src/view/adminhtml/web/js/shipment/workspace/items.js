/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * What the shipment carries in Magento: items the order has left, with the quantity of each, so an
 * order can go out in parts. With nothing left, it replaces a cancelled Polkurier order on that
 * order's Magento shipment. The cash on delivery follows what the shipment collects and the insurance
 * what it's worth, until either is set by hand.
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
            // The insurance and the cash on delivery this part last set, or started with: anything
            // else was set by hand.
            this.followed = null;
            this.followedCod = null;
            // Whether the shipment was the whole order, and included the shipping, when last seen.
            this.wasWhole = null;
            this.wasIncluded = null;
            // The cash on delivery when last seen: changed elsewhere, the insurance follows it.
            this.seenCod = null;
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
            if (this.followed === null && items.length) {
                this.followed = rules.amount(data.insurance_amount);
                this.followedCod = rules.amount(data.cod_amount);
                this.wasWhole = rules.shipmentWorth(data).whole;
            }
            // Including the shipping and handling, or not, is asking for both amounts again.
            if (this.wasIncluded !== null && Boolean(data.shipping_included) !== this.wasIncluded) {
                this.wasIncluded = Boolean(data.shipping_included);
            this.followCod(data);
                this.follow(true);
            }
            this.wasIncluded = Boolean(data.shipping_included);
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

        /**
         * The cash on delivery changed by hand or switched on: the insurance, unless set by hand
         * or off, goes with it.
         *
         * @param {Object} data
         */
        followCod: function (data) {
            const cod = rules.amount(data.cod_amount),
                insurance = rules.amount(data.insurance_amount),
                before = this.seenCod;

            this.seenCod = cod;
            if (before === null || Math.abs(cod - before) < 0.005 || insurance <= 0) {
                return;
            }
            // Set by hand: neither what it last followed, nor the old cash on delivery, nor the value.
            if (![this.followed, before, rules.amount(data.shipment_value)].some(function (amount) {
                return amount !== null && Math.abs(insurance - amount) < 0.005;
            })) {
                return;
            }
            this.followed = rules.insuranceFor(cod, rules.amount(data.shipment_value));
            this.set('insurance_amount', this.followed.toFixed(2));
        },

        /**
         * Keeps the cash on delivery at what the shipment collects (rules.codFor) and the insurance
         * at what it's worth, unless either is off or, unless forced, was set by hand.
         *
         * @param {Boolean} [force] set them whatever they are, as including the shipping asks
         */
        follow: function (force) {
            let data = this.get(),
                worth = rules.shipmentWorth(data);

            // Going from the whole order to part of it starts without the shipping and handling.
            if (this.wasWhole && !worth.whole && data.shipping_included) {
                this.wasWhole = false;
                this.wasIncluded = false;
                this.set('shipping_included', false);
                data = this.get();
                worth = rules.shipmentWorth(data);
            }
            this.wasWhole = worth.whole;
            const value = worth.value,
                before = rules.amount(data.shipment_value),
                insurance = rules.amount(data.insurance_amount),
                codBefore = rules.amount(data.cod_amount),
                // What this part set, or what the insurance switch fills in: not set by hand.
                automatic = [this.followed, before, codBefore, Math.max(before, codBefore)];
            let cod = codBefore,
                next = value;

            this.set('shipment_value', value);
            if (codBefore > 0 && !data.replacing && (force || this.followedCod !== null && Math.abs(codBefore - this.followedCod) < 0.005)) {
                cod = Math.round(rules.codFor(rules.amount(data.cod_remaining), worth) * 100) / 100;
                this.followedCod = cod;
                this.set('cod_amount', cod.toFixed(2));
            }
            if (insurance <= 0 || !force && !automatic.some(function (amount) {
                return Math.abs(insurance - amount) < 0.005;
            })) {
                return;
            }
            // What it collects, or else what it's worth: the parts add up to the order total.
            next = rules.insuranceFor(cod, value);
            this.followed = next;
            this.seenCod = cod;
            this.set('insurance_amount', next.toFixed(2));
        }
    });
});
