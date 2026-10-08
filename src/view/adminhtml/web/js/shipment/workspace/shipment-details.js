/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The carrier, the contents and the price of the shipment, each with a way to change it, and
 * Polkurier's current messages about the carrier under it; then what the shipment is worth.
 */
define([
    'ko',
    'mage/translate',
    './part',
    './rules'
], function (ko, $t, Part, rules) {
    'use strict';

    return Part.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/workspace/shipment-details',
            // The form's modals, from its XML.
            carrierModal: '',
            shipmentModal: ''
        },

        initState: function () {
            this.carrierName = ko.observable('');
            this.contents = ko.observable('');
            this.price = ko.observable(null);
            this.quoting = ko.observable(false);
            this.unpriced = ko.observable(false);
            this.priceMessage = ko.observable('');
            this.carrierNotices = ko.observableArray([]);
            this.carrierMissing = ko.observable(false);
            this.worth = ko.observable(null);
            // Part of the order only: the whole order includes it whatever was asked.
            this.shippingIncluded = ko.observable(false);
            this.shippingIncluded.subscribe(function (included) {
                if (included !== Boolean(this.get('shipping_included'))) {
                    this.set('shipping_included', included);
                }
            }, this);
        },

        refresh: function () {
            const data = this.get() || {},
                carriers = data.carriers || {},
                quotes = this.source.get('quotes') || null,
                quote = quotes && data.carrier_code ? quotes[data.carrier_code] || null : null;

            this.carrierName(data.carrier_code ? (carriers[data.carrier_code] || {}).name || data.carrier_code : '');
            // Marked once placing was tried, as everything else missing is.
            this.carrierMissing(!data.carrier_code && this.validated());
            this.contents(data.description || '');
            this.quoting(!!this.source.get('quoting'));
            this.price(quote ? {net: this.money(quote.net), gross: this.money(quote.gross)} : null);
            this.unpriced(!!(quotes && data.carrier_code && !quote));
            this.priceMessage(this.source.get('quoteMessage') || '');
            this.carrierNotices((data.carrier_notices || []).filter(function (notice) {
                return notice.carrier === data.carrier_code;
            }));
            this.worth(this.describeWorth(data));
            this.shippingIncluded(Boolean(data.shipping_included));
        },

        /**
         * What the shipment is worth, with what that comes from (rules.shipmentWorth); null with
         * nothing to ship and nothing to replace.
         *
         * @param {Object} data
         * @returns {Object|null}
         */
        describeWorth: function (data) {
            const replacing = data.replacing,
                worth = rules.shipmentWorth(data);

            // Nothing to ship and nothing to replace: there's no shipment to be worth anything.
            if (!replacing && !(data.can_ship !== false && (data.items || []).length)) {
                return null;
            }

            return {
                productsLabel: replacing
                    ? $t('Products on Magento shipment #%1').replace('%1', replacing.increment_id)
                    : $t('Products in this shipment'),
                products: this.money(worth.products),
                shipping: this.money(rules.amount(data.shipping_charged)),
                total: this.money(worth.order_total),
                value: this.money(worth.value),
                // For a priced part of the order, the merchant is asked whether it carries the
                // shipping and handling. Not for the part carrying the rest, which includes it as it
                // collects it, nor once another part includes it.
                canIncludeShipping: !replacing && !worth.whole && !worth.rest
                    && !worth.shipping_covered && worth.products > 0,
                basis: worth.whole
                    ? $t('the order total, as all of the order ships')
                    : (worth.products > 0
                        ? (worth.with_shipping
                            ? (worth.rest && !data.shipping_included
                                ? $t('the products and the shipping and handling, as this part carries the rest of the order')
                                : $t('the products and the shipping and handling, as only part of the order ships'))
                            : $t('the products, as only part of the order ships'))
                        : (worth.chosen === false
                            ? $t('the order total, as no products are chosen')
                            : $t('the order total, as the chosen products have no price')))
            };
        },

        changeCarrier: function () {
            this.openModal(this.carrierModal);
        },

        changeContents: function () {
            this.openModal(this.shipmentModal);
        },

        /**
         * @returns {String}
         */
        noPrice: function () {
            if (this.quoting()) {
                return $t('Updating prices…');
            }

            // The reason is shown under it.
            if (this.unpriced()) {
                return $t('No price');
            }

            return this.priceMessage() || $t('No price yet');
        }
    });
});
