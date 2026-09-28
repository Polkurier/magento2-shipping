/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Opens the pickup point map in a dialog; shared by the regular and the multi-address checkout.
 */
define(['mage/translate'], function ($t) {
    'use strict';

    let widget = null;

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
         * @param {String} searchQuery where the map starts, e.g. "00-001 Warszawa"
         * @param {Function} onChoose called with {id, label}
         */
        open: function (config, providers, searchQuery, onChoose) {
            loadWidget(config.baseUrl).then(function () {
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
            }, function () {
                window.alert($t('The pickup point map could not be loaded. Try again later.'));
            });
        }
    };
});
