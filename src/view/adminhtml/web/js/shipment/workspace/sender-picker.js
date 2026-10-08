/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The sender, chosen from the address book, shown in full, or one typed in for the shipment.
 * Choosing a stored one closes the modal; a new one shows its fields below (./new-entry).
 */
define([
    'Magento_Ui/js/form/element/select',
    'uiRegistry',
    'ko',
    'mage/translate'
], function (Select, registry, ko, $t) {
    'use strict';

    return Select.extend({
        defaults: {
            // The modal it sits in, from the form's XML.
            modal: ''
        },

        initialize: function () {
            this._super();
            this.tiles = ko.pureComputed(this.buildTiles, this);

            return this;
        },

        /**
         * @returns {Array}
         */
        buildTiles: function () {
            const senders = this.source.get('data.senders') || {},
                store = this.source.get('data.store_sender'),
                selected = this.value() || '';

            // As given: the select uses the store's address, with the value '', as its caption.
            return (this.initialOptions || this.options() || []).filter(function (option) {
                return option.value !== '' || store;
            }).map(function (option) {
                const address = option.value === '' ? store : senders[String(option.value)] || {};

                if (option.value === 'new') {
                    return {
                        value: 'new',
                        title: $t('A new address'),
                        lines: [$t('Typed in for this shipment, and saved for later if you like.')],
                        isDefault: false,
                        selected: selected === 'new'
                    };
                }

                return {
                    value: String(option.value),
                    title: option.value === '' ? $t('The store\'s address') : address.label || option.label,
                    lines: [
                        [address.company, address.person].filter(Boolean).join(', '),
                        address.street,
                        address.city,
                        [address.phone, address.email].filter(Boolean).join(', '),
                        // Where its parcels are dropped off, for carriers that take them at points.
                        address.point_id ? $t('Drop-off point') + ': ' + address.point_id : ''
                    ].filter(Boolean),
                    isDefault: !!address.default,
                    selected: String(option.value) === String(selected)
                };
            });
        },

        /**
         * @param {Object} tile
         */
        choose: function (tile) {
            const address = (this.source.get('data.senders') || {})[String(tile.value)];

            this.value(tile.value);
            // Handed over at that address's drop-off point, if it has one.
            this.source.set('data.sender_point_id', address && address.point_id ? String(address.point_id) : '');
            // A new address's fields are filled in below first, so the modal stays open.
            if (tile.value === 'new') {
                return;
            }
            registry.get(this.modal, function (modal) {
                modal.closeModal();
            });
        },

        /**
         * @param {Object} tile
         * @param {KeyboardEvent} event
         * @returns {Boolean}
         */
        onKey: function (tile, event) {
            if (event.key === 'Enter' || event.key === ' ') {
                this.choose(tile);

                return false;
            }

            return true;
        }
    });
});
