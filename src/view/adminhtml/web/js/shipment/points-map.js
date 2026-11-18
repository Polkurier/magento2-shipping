/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * Opens the Polkurier pickup point map in the admin and resolves with the point chosen.
 *
 * The admin loads Prototype.js, which replaces native Array methods (map, find, reverse and
 * others) that the map widget relies on, and the widget overflows the stack there. So the widget
 * runs in a blank same-origin iframe laid over the page, with clean built-ins of its own; the
 * iframe is only shown while the map dialog is open. The widget's script and styles are loaded
 * into it once, from the map host, the first time the map is needed.
 */
define([
    'mage/translate',
    'Magento_Ui/js/modal/alert',
    'Polkurier_Shipping/js/points-map-widget'
], function ($t, alert, pointsMapWidget) {
    'use strict';

    let frame = null,
        widget = null;

    /**
     * @returns {HTMLIFrameElement}
     */
    function getFrame() {
        if (frame === null) {
            frame = document.createElement('iframe');
            frame.className = 'polkurier-points-map-frame';
            frame.title = $t('Pickup point map');
            frame.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;border:0;margin:0;'
                + 'padding:0;background:transparent;z-index:2147483000;display:none;';
            document.body.appendChild(frame);
            frame.contentDocument.open();
            frame.contentDocument.write('<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
                + '<body style="margin:0;background:transparent;"></body></html>');
            frame.contentDocument.close();
        }

        return frame;
    }

    /**
     * @param {Boolean} visible
     */
    function showFrame(visible) {
        const element = getFrame();

        element.style.display = visible ? 'block' : 'none';
        if (visible) {
            element.focus();
        }
    }

    /**
     * @param {String} base
     * @returns {Promise<Window>} the iframe's window, with the widget loaded
     */
    function loadWidget(base) {
        if (widget === null) {
            const target = getFrame().contentWindow;

            widget = pointsMapWidget.loadInto(target.document, base).then(function () {
                return target;
            }, function (error) {
                widget = null;

                return Promise.reject(error);
            });
        }

        return widget;
    }

    return {
        /**
         * @param {Object} [map]
         * @returns {Boolean} whether the map can be opened: with a token, or a way to get one
         */
        canOpen: function (map) {
            return !!(map && (map.token || map.tokenUrl));
        },

        /**
         * The point's details, by its code, with a point chosen on the map added: its name, or its
         * description when it has none, and its address in one line.
         *
         * @param {Object} details the points' names and addresses, by code
         * @param {String} pointId
         * @param {Object} point as the map returns it
         * @returns {Object} a copy of details with the point
         */
        addPoint: function (details, pointId, point) {
            const copy = Object.assign({}, details || {});

            copy[pointId] = {
                name: point.name || point.description || '',
                label: pointsMapWidget.formatAddress(point)
            };

            return copy;
        },

        /**
         * The point's name and address in one line, '' when neither is known.
         *
         * @param {Object} details the points' names and addresses, by code
         * @param {String} pointId
         * @returns {String}
         */
        describe: function (details, pointId) {
            const point = (details || {})[pointId];

            return point ? [point.name, point.label].filter(Boolean).join(', ') : '';
        },

        /**
         * @param {{token: String, tokenExpires: Number, tokenUrl: String, baseUrl: String}} map
         * @param {String[]} providers the carrier's point providers
         * @param {String} searchQuery where the map should start: the postcode, e.g. "00-001"
         * @param {Function} onChoose called with the point's id and the point
         * @param {String[]} [functions] only points that do these, e.g. ['send'] for drop-off points
         */
        open: function (map, providers, searchQuery, onChoose, functions) {
            const url = map.tokenUrl + (map.tokenUrl.indexOf('?') === -1 ? '?' : '&') + 'isAjax=true';

            pointsMapWidget.fetchFreshToken(map, url).then(function () {
                return loadWidget(map.baseUrl);
            }).then(function (target) {
                // The providers array is made in the iframe, so it has the widget's built-ins too.
                const options = {
                    token: map.token,
                    baseUrl: map.baseUrl,
                    providers: target.Array.from(providers || []),
                    searchQuery: searchQuery
                };

                if (functions && functions.length) {
                    options.functions = target.Array.from(functions);
                }
                const instance = new target.POLKURIER.PointsMap(options);

                instance.on('dialog:closed', function () {
                    showFrame(false);
                });
                instance.onConfirm(function (point) {
                    onChoose(String(point.id), point);
                    instance.closeMapDialog();
                });
                showFrame(true);
                instance.showMapDialog();
            }).catch(function () {
                // Also when the widget fails to start once loaded.
                showFrame(false);
                alert({title: $t('Pickup point map'), content: $t('The pickup point map could not be loaded.')});
            });
        }
    };
});
