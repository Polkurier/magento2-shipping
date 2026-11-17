/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * Opens the pickup point map in a dialog; shared by the regular and the multi-address checkout.
 */
define([
    'mage/translate',
    'Magento_Ui/js/modal/alert',
    'Polkurier_Shipping/js/points-map-widget'
], function ($t, alert, pointsMapWidget) {
    'use strict';

    let widget = null;

    /**
     * Loads the map widget into the page once.
     *
     * @param {String} base
     * @returns {Promise}
     */
    function loadWidget(base) {
        if (widget === null) {
            widget = pointsMapWidget.loadInto(document, base).catch(function (error) {
                widget = null;

                return Promise.reject(error);
            });
        }

        return widget;
    }

    return {
        /**
         * @param {Object} config the map settings from the checkout configuration
         * @param {Array} providers point networks of the delivery method
         * @param {String} searchQuery where the map starts: the postcode, e.g. "00-001"
         * @param {Function} onChoose called with {id, label, cod}
         */
        open: function (config, providers, searchQuery, onChoose) {
            pointsMapWidget.fetchFreshToken(config).then(function () {
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
                    onChoose({id: point.id, label: pointsMapWidget.formatAddress(point), cod: point.cod});
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
