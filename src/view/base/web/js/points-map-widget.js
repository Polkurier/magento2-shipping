/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * The pickup point map widget as the admin and the storefront both use it: its token, kept fresh;
 * its script and styles, loaded into a document; and a point it returns, as an address in one line.
 *
 * The token runs out some hours after it's issued. A page is given one with when it stops working,
 * and asks map.tokenUrl for a fresh one when it has none or it's about to run out, so a page left
 * open for a long time still shows the map.
 */
define([], function () {
    'use strict';

    // How many seconds before a token runs out a fresh one is asked for instead.
    const REFRESH_BEFORE = 600;

    return {
        /**
         * @param {{token: String, tokenExpires: Number, tokenUrl: String}} map updated in place with a
         *     fresh token
         * @param {String} [url] where to ask, when it isn't map.tokenUrl as it is
         * @returns {Promise<String>} the token to open the map with
         */
        fetchFreshToken: function (map, url) {
            const now = Date.now() / 1000;

            if (map.token && (!map.tokenExpires || map.tokenExpires - now > REFRESH_BEFORE)) {
                return Promise.resolve(map.token);
            }

            return fetch(url || map.tokenUrl, {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
            }).then(function (response) {
                return response.ok ? response.json() : Promise.reject(new Error('No map token'));
            }).then(function (answer) {
                if (!answer || !answer.token) {
                    return Promise.reject(new Error('No map token'));
                }
                map.token = answer.token;
                map.tokenExpires = answer.expires || 0;

                return map.token;
            });
        },

        /**
         * Loads the widget's styles and script into a document. They must be loaded without integrity
         * or crossorigin attributes, which the map host does not support.
         *
         * @param {Document} doc
         * @param {String} base the map host's address, ending in a slash
         * @returns {Promise} resolved once the script has run
         */
        loadInto: function (doc, base) {
            return new Promise(function (resolve, reject) {
                const style = doc.createElement('link'),
                    script = doc.createElement('script');

                style.rel = 'stylesheet';
                style.href = base + 'assets/dist/points-map.css';
                doc.head.appendChild(style);
                script.src = base + 'assets/dist/points-map.bundle.js';
                script.onload = resolve;
                script.onerror = reject;
                doc.head.appendChild(script);
            });
        },

        /**
         * @param {Object} point as the widget returns it
         * @returns {String} its address in one line, as Polkurier gives it or made of its parts
         */
        formatAddress: function (point) {
            return point.address
                || [point.street, [point.zip, point.city].filter(Boolean).join(' ')].filter(Boolean).join(', ');
        }
    };
});
