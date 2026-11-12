/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * The carrier, chosen in its modal from a list that shows what each carrier would charge for the
 * shipment as it stands, cheapest first. Carriers that can't take it, such as those without a
 * service chosen, are tucked away until asked for. Choosing one closes the modal.
 *
 * Publishes what the chosen carrier supports as data.features, which the rest of the form imports
 * to show, hide and narrow its fields. A pickup point belongs to the carrier it was chosen for:
 * changing the carrier puts it aside, and going back to that carrier brings it back.
 */
define([
    'Magento_Ui/js/form/element/select',
    'uiRegistry',
    'ko',
    'mage/translate',
    './rules'
], function (Select, registry, ko, $t, rules) {
    'use strict';

    return Select.extend({
        defaults: {
            // The modal it sits in, from the form's XML.
            modal: '',
            quotes: null,
            quoting: false,
            imports: {
                quotes: '${ $.provider }:quotes',
                quoting: '${ $.provider }:quoting'
            },
            tracks: {
                quotes: true,
                quoting: true
            },
            listens: {
                value: 'publishFeatures'
            }
        },

        initialize: function () {
            this._super();
            this.publishFeatures(this.value());
            this.search = ko.observable('');
            this.showAll = ko.observable(false);
            this.searchPlaceholder = $t('Search carriers');
            this.tiles = ko.pureComputed(this.buildTiles, this);
            this.hiddenCount = ko.pureComputed(function () {
                return this.getAllTiles().length - this.tiles().length;
            }, this);

            return this;
        },

        /**
         * @returns {Array}
         */
        getAllTiles: function () {
            const carriers = this.source.get('data.carriers') || {},
                quotes = this.quotes,
                currency = this.source.get('data.currency') || 'PLN',
                query = this.search().trim().toLowerCase(),
                selected = this.value();

            const data = this.source.get('data') || {},
                serviceLabels = (data.choices || {}).services || {};

            return (this.options() || []).filter(function (option) {
                return option.value !== '' && (!query || option.label.toLowerCase().indexOf(query) !== -1);
            }).map(function (option) {
                const carrier = carriers[option.value] || {},
                    // The chosen services it doesn't offer: it can't take the shipment as it is.
                    lacking = rules.findLackingServices(data, carrier.services).map(function (service) {
                        return service === 'COVER_ADDRESS_SENDER'
                            ? $t('Another sender on the label')
                            : serviceLabels[service] || service;
                    }),
                    quote = quotes && !lacking.length ? quotes[option.value] || null : null;

                return {
                    value: option.value,
                    name: carrier.name || option.label,
                    selected: option.value === selected,
                    priced: !!quote,
                    gross: quote ? quote.gross : null,
                    priceText: quote
                        ? rules.formatMoney(quote.net, currency) + ' ' + $t('net')
                        : (quotes ? $t('not available') : '—'),
                    grossText: quote ? rules.formatMoney(quote.gross, currency) + ' ' + $t('gross') : '',
                    point: carrier.point && carrier.point !== 'NONE',
                    pointOnly: carrier.point === 'REQUIRED',
                    cod: !!carrier.cod,
                    lacking: lacking.length ? $t('Does not offer: %1').replace('%1', lacking.join(', ')) : ''
                };
            });
        },

        /**
         * @returns {Array}
         */
        buildTiles: function () {
            const tiles = this.getAllTiles(),
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
            registry.get(this.modal, function (modal) {
                modal.closeModal();
            });
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
        },

        /**
         * @param {String} previous
         * @param {String} code
         */
        swapPoint: function (previous, code) {
            this.source.set('data.recipient_point_id', rules.swapPoint(
                this.pointsByCarrier,
                previous,
                code,
                this.source.get('data.recipient_point_id') || ''
            ));
        },

        /**
         * @param {String} code
         */
        publishFeatures: function (code) {
            // Also called while the field is set up, with the carrier the form starts with.
            if (this.pointsByCarrier === undefined) {
                this.pointsByCarrier = {};
                this.carrier = code;
            } else if (code !== this.carrier) {
                this.swapPoint(this.carrier, code);
                this.carrier = code;
                // Points belong to a carrier's network: the drop-off point becomes the account's
                // for the carrier.
                this.source.set('data.sender_point_id', rules.buildFeatures(
                    (this.source.get('data.carriers') || {})[code] || null
                ).default_sender_point);
            }
            const carriers = this.source.get('data.carriers') || {},
                carrier = carriers[code] || null,
                features = rules.buildFeatures(carrier);

            this.source.set('data.features', features);
            // Parcels must be dropped off with a carrier that takes them only at points, and can't
            // be with one that only collects them.
            if (carrier && (features.sender_point === 'REQUIRED'
                || (!carrier.drop_off && this.source.get('data.pickup_mode') === 'drop_off'))
            ) {
                this.source.set(
                    'data.pickup_mode',
                    rules.getDefaultHandover(features, String(this.source.get('data.sender_point_id') || ''))
                );
            }
        }
    });
});
