/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Opens the pickup point map in a dialog; shared by the regular and the multi-address checkout.
 *
 * The map needs a token, which runs out some hours after it's issued. The page is given one with
 * when it stops working; opening the map asks for a fresh one when there's none or it's about to
 * run out, so a page left open for a long time still shows the map.
 */
define(['mage/translate', 'Magento_Ui/js/modal/alert'], function ($t, alert) {
    'use strict';

    // Seconds before a token runs out when a fresh one is asked for instead.
    const REFRESH_BEFORE = 600;

    let widget = null;

    /**
     * @param {Object} config the map settings; given a fresh token in place
     * @returns {Promise<String>} the token to open the map with
     */
    function freshToken(config) {
        const now = Date.now() / 1000;

        if (config.token && (!config.tokenExpires || config.tokenExpires - now > REFRESH_BEFORE)) {
            return Promise.resolve(config.token);
        }
        if (!config.tokenUrl) {
            return config.token ? Promise.resolve(config.token) : Promise.reject(new Error('No map token'));
        }

        return fetch(config.tokenUrl, {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        }).then(function (response) {
            return response.ok ? response.json() : Promise.reject(new Error('No map token'));
        }).then(function (answer) {
            if (!answer || !answer.token) {
                return Promise.reject(new Error('No map token'));
            }
            config.token = answer.token;
            config.tokenExpires = answer.expires || 0;

            return config.token;
        });
    }

    /**
     * Loads the map widget once. It must be loaded without integrity or crossorigin
     * attributes, which the maps host does not support.
     *
     * @param {String} base
     * @returns {Promise}
     */
    function loadWidget(base) {
        if (widget === null) {
            widget = new Promise(function (resolve, reject) {
                const style = document.createElement('link'),
                    script = document.createElement('script');

                style.rel = 'stylesheet';
                style.href = base + 'assets/dist/points-map.css';
                document.head.appendChild(style);
                script.src = base + 'assets/dist/points-map.bundle.js';
                script.onload = resolve;
                script.onerror = function () {
                    widget = null;
                    reject();
                };
                document.head.appendChild(script);
            });
        }

        return widget;
    }

    return {
        /**
         * @param {Object} config the map settings from the checkout configuration
         * @param {Array} providers point networks of the delivery method
         * @param {String} searchQuery where the map starts: the postcode, e.g. "00-001"
         * @param {Function} onChoose called with {id, label}
         */
        open: function (config, providers, searchQuery, onChoose) {
            freshToken(config).then(function () {
                return loadWidget(config.baseUrl);
            }).then(function () {
                const map = new window.POLKURIER.PointsMap({
                    token: config.token,
                    baseUrl: config.baseUrl,
                    providers: providers,
                    functions: config.functions,
                    latitude: config.latitude,
                    longitude: config.longitude,
                    zoom: config.zoom,
                    showList: config.showList,
                    showSearchInput: config.showSearch,
                    searchQuery: searchQuery
                });

                map.onConfirm(function (point) {
                    onChoose({
                        id: point.id,
                        label: point.address || [point.street, point.zip, point.city].filter(Boolean).join(' ')
                    });
                    map.closeMapDialog();
                });
                map.showMapDialog();
            }).catch(function () {
                // Also when the widget fails to start once loaded.
                alert({content: $t('The pickup point map could not be loaded. Try again later.')});
            });
        }
    };
});
