/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The carrier, the contents and the price of the shipment, each with a way to change it, and
 * Polkurier's current messages about the carrier under it.
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
            this.shipmentType = ko.observable('');
            this.price = ko.observable(null);
            this.quoting = ko.observable(false);
            this.unpriced = ko.observable(false);
            this.priceMessage = ko.observable('');
            this.carrierNotices = ko.observableArray([]);
        },

        refresh: function () {
            const data = this.get() || {},
                carriers = data.carriers || {},
                quotes = this.source.get('quotes') || null,
                quote = quotes && data.carrier_code ? quotes[data.carrier_code] || null : null;

            this.carrierName(data.carrier_code ? (carriers[data.carrier_code] || {}).name || data.carrier_code : '');
            this.contents(data.description || '');
            this.shipmentType(this.choice('shipment_type', data.shipment_type));
            this.quoting(!!this.source.get('quoting'));
            this.price(quote ? {net: this.money(quote.net), gross: this.money(quote.gross)} : null);
            this.unpriced(!!(quotes && data.carrier_code && !quote));
            this.priceMessage(this.source.get('quoteMessage') || '');
            this.carrierNotices((data.carrier_notices || []).filter(function (notice) {
                return notice.carrier === data.carrier_code;
            }));
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
                return $t('Pricing…');
            }

            return this.priceMessage() || '—';
        },

        filled: rules.filled
    });
});
