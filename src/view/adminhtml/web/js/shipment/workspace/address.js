/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The sender's or the recipient's address, as it will go to the carrier.
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
            template: 'Polkurier_Shipping/shipment/workspace/address',
            kind: 'recipient'
        },

        initState: function () {
            this.lines = ko.observableArray([]);
            this.caption = ko.observable('');
            this.missing = ko.observable('');
        },

        refresh: function () {
            const address = this.kind === 'sender' ? this.sender() : this.recipient();

            this.missing(address ? '' : $t('No sender address: choose one, or add one under Polkurier > Settings > Addresses.'));
            this.caption(address && address.caption ? address.caption : '');
            this.lines(address ? [
                {text: address.company, strong: true},
                {text: address.person, strong: true},
                {text: address.street},
                {text: [address.city, address.country && address.country !== 'PL' ? '(' + address.country + ')' : ''].filter(rules.filled).join(' ')},
                {text: [address.phone, address.email].filter(rules.filled).join(', '), muted: true}
            ].filter(function (line) {
                return rules.filled(line.text);
            }) : []);
        },

        /**
         * @returns {Object|null}
         */
        sender: function () {
            const id = this.get('sender_address_id'),
                address = rules.filled(id) ? (this.get('senders') || {})[String(id)] : this.get('store_sender');

            if (!address) {
                return null;
            }

            return Object.assign({}, address, {caption: rules.filled(id) ? address.label : $t('The store\'s address')});
        },

        /**
         * @returns {Object}
         */
        recipient: function () {
            const data = this.get() || {},
                number = [data.recipient_house_number, data.recipient_flat_number].filter(rules.filled).join('/');

            return {
                company: data.recipient_company,
                person: data.recipient_person,
                street: [data.recipient_street, number].filter(rules.filled).join(' '),
                city: [data.recipient_postcode, data.recipient_city].filter(rules.filled).join(' '),
                country: data.recipient_country,
                phone: data.recipient_phone,
                email: data.recipient_email
            };
        }
    });
});
