/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The sender, chosen from the address book, shown in full. Choosing one closes the modal.
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

            return (this.options() || []).filter(function (option) {
                return option.value !== '' || store;
            }).map(function (option) {
                const address = option.value === '' ? store : senders[String(option.value)] || {};

                return {
                    value: String(option.value),
                    title: option.value === '' ? $t('The store\'s address') : address.label || option.label,
                    lines: [
                        [address.company, address.person].filter(Boolean).join(', '),
                        address.street,
                        address.city,
                        [address.phone, address.email].filter(Boolean).join(', ')
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
            this.value(tile.value);
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
