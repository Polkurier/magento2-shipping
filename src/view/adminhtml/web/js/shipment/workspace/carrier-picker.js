/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The carrier, chosen from a list that shows what each carrier would charge for the shipment as it
 * stands, cheapest first. Carriers that can't take it are tucked away until asked for.
 */
define([
    'Polkurier_Shipping/js/shipment/carrier',
    'ko',
    'mage/translate'
], function (Carrier, ko, $t) {
    'use strict';

    return Carrier.extend({
        defaults: {
            quotes: null,
            quoting: false,
            imports: {
                quotes: '${ $.provider }:quotes',
                quoting: '${ $.provider }:quoting'
            },
            tracks: {
                quotes: true,
                quoting: true
            }
        },

        initialize: function () {
            this._super();
            this.search = ko.observable('');
            this.showAll = ko.observable(false);
            this.searchPlaceholder = $t('Search carriers');
            this.tiles = ko.pureComputed(this.buildTiles, this);
            this.hiddenCount = ko.pureComputed(function () {
                return this.allTiles().length - this.tiles().length;
            }, this);

            return this;
        },

        /**
         * @returns {Array}
         */
        allTiles: function () {
            const carriers = this.source.get('data.carriers') || {},
                quotes = this.quotes,
                currency = this.source.get('data.currency') || 'PLN',
                query = this.search().trim().toLowerCase(),
                selected = this.value();

            return (this.options() || []).filter(function (option) {
                return option.value !== '' && (!query || option.label.toLowerCase().indexOf(query) !== -1);
            }).map(function (option) {
                const carrier = carriers[option.value] || {},
                    quote = quotes ? quotes[option.value] || null : null;

                return {
                    value: option.value,
                    name: carrier.name || option.label,
                    code: option.value,
                    selected: option.value === selected,
                    priced: !!quote,
                    gross: quote ? quote.gross : null,
                    priceText: quote
                        ? quote.gross.toFixed(2) + ' ' + currency
                        : (quotes ? $t('not available') : '—'),
                    point: carrier.point && carrier.point !== 'NONE',
                    pointOnly: carrier.point === 'REQUIRED',
                    cod: !!carrier.cod
                };
            });
        },

        /**
         * @returns {Array}
         */
        buildTiles: function () {
            const tiles = this.allTiles(),
                priced = tiles.filter(function (tile) {
                    return tile.priced;
                }),
                cheapest = priced.length ? Math.min.apply(null, priced.map(function (tile) {
                    return tile.gross;
                })) : null,
                visible = this.showAll() || !this.quotes || this.search().trim() !== ''
                    ? tiles
                    : tiles.filter(function (tile) {
                        return tile.priced || tile.selected;
                    });

            return visible.map(function (tile) {
                tile.cheapest = tile.priced && tile.gross === cheapest;

                return tile;
            }).sort(function (a, b) {
                if (a.priced !== b.priced) {
                    return a.priced ? -1 : 1;
                }

                return a.priced ? a.gross - b.gross : a.name.localeCompare(b.name);
            });
        },

        /**
         * @param {Object} tile
         */
        choose: function (tile) {
            this.value(tile.value);
        },

        /**
         * @param {Object} tile
         * @param {KeyboardEvent} event
         * @returns {Boolean} whether the browser should handle the key
         */
        onKey: function (tile, event) {
            if (event.key === 'Enter' || event.key === ' ') {
                this.choose(tile);

                return false;
            }

            return true;
        },

        toggleAll: function () {
            this.showAll(!this.showAll());
        }
    });
});
