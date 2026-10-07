/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Opens the Polkurier pickup point map in the admin and resolves with the point chosen.
 *
 * The admin loads Prototype.js, which replaces native Array methods (map, find, reverse and
 * others) that the map widget relies on, and the widget overflows the stack there. So the widget
 * runs in a blank same-origin iframe laid over the page, with clean built-ins of its own; the
 * iframe is only shown while the map dialog is open. The widget's script and styles are loaded
 * into it once, from the map host, the first time the map is needed.
 *
 * The map's token runs out some hours after it's issued: opening the map asks map.tokenUrl for a
 * fresh one when the page has none or it's about to run out, so a screen left open still works.
 */
define(['mage/translate', 'Magento_Ui/js/modal/alert'], function ($t, alert) {
    'use strict';

    // Seconds before a token runs out when a fresh one is asked for instead.
    const REFRESH_BEFORE = 600;

    let frame = null,
        widget = null;

    /**
     * @param {Object} map the map settings; given a fresh token in place
     * @returns {Promise<String>}
     */
    function freshToken(map) {
        const now = Date.now() / 1000;

        if (map.token && (!map.expires || map.expires - now > REFRESH_BEFORE)) {
            return Promise.resolve(map.token);
        }
        if (!map.tokenUrl) {
            return map.token ? Promise.resolve(map.token) : Promise.reject(new Error('No map token'));
        }

        return fetch(map.tokenUrl + (map.tokenUrl.indexOf('?') === -1 ? '?' : '&') + 'isAjax=true', {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
            return response.ok ? response.json() : Promise.reject(new Error('No map token'));
        }).then(function (answer) {
            if (!answer || !answer.token) {
                return Promise.reject(new Error('No map token'));
            }
            map.token = answer.token;
            map.expires = answer.expires || 0;

            return map.token;
        });
    }

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
            widget = new Promise(function (resolve, reject) {
                const target = getFrame().contentWindow,
                    doc = target.document,
                    style = doc.createElement('link'),
                    script = doc.createElement('script');

                style.rel = 'stylesheet';
                style.href = base + 'assets/dist/points-map.css';
                doc.head.appendChild(style);
                script.src = base + 'assets/dist/points-map.bundle.js';
                script.onload = function () {
                    resolve(target);
                };
                script.onerror = function () {
                    widget = null;
                    reject();
                };
                doc.head.appendChild(script);
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
         * The point's address in one line, for showing next to its code.
         *
         * @param {Object} point as the map returns it
         * @returns {String}
         */
        describe: function (point) {
            if (!point) {
                return '';
            }

            return point.address
                || [point.street, [point.zip, point.city].filter(Boolean).join(' ')].filter(Boolean).join(', ');
        },

        /**
         * @param {{token: String, baseUrl: String}} map
         * @param {String[]} providers the carrier's point providers
         * @param {String} searchQuery where the map should start: the postcode, e.g. "00-001"
         * @param {Function} onChoose called with the point's id and the point
         */
        open: function (map, providers, searchQuery, onChoose) {
            freshToken(map).then(function () {
                return loadWidget(map.baseUrl);
            }).then(function (target) {
                // The providers array is made in the iframe, so it has the widget's built-ins too.
                const instance = new target.POLKURIER.PointsMap({
                    token: map.token,
                    baseUrl: map.baseUrl,
                    providers: target.Array.from(providers || []),
                    searchQuery: searchQuery
                });

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
