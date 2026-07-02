/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
define([
    'uiComponent',
    'ko',
    'Magento_Checkout/js/model/quote',
    'mage/translate'
], function (Component, ko, quote, $t) {
    'use strict';

    const mapConfig = window.checkoutConfig.polkurier.map;

    return Component.extend({
        defaults: {
            allowedMethods: mapConfig.allowedMethods,
            methodProviders: mapConfig.methodProviders,
            mapToken: mapConfig.token,
            mapBaseUrl: mapConfig.baseUrl,
            mapDefaultLatitude: mapConfig.defaultLatitude,
            mapDefaultLongitude: mapConfig.defaultLongitude,
            mapDefaultZoom: mapConfig.defaultZoom,
            mapShowList: mapConfig.showList,
            mapShowSearchInput: mapConfig.showSearchInput,
            mapRequiredPointFunctions: mapConfig.requiredPointFunctions
        },
        initialize: function () {
            this._super();
            this.selectedPoint = ko.observable();
            this.selectedPoints = {};
            for (const allowedMethod of this.allowedMethods) {
                this.selectedPoints[allowedMethod] = ko.observable();
            }
        },
        hasModalButton: function (methodCode) {
            return this.allowedMethods.includes(methodCode);
        },
        openModalWindow: function (methodCode) {
            const self = this;
            const map = new POLKURIER.PointsMap({
                token: this.mapToken,
                baseUrl: this.mapBaseUrl,
                providers: this.methodProviders[methodCode],
                functions: this.mapRequiredPointFunctions,
                latitude: this.mapDefaultLatitude,
                longitude: this.mapDefaultLongitude,
                zoom: this.mapDefaultZoom,
                showList: this.mapShowList,
                showSearchInput: this.mapShowSearchInput
            });
            map.showMapDialog();
            map.onConfirm(function (point) {
                self.updateQuote(point);
                self.setSelectedPoint(point, methodCode);
                map.closeMapDialog();
            });
        },
        updateQuote: function (point) {
            quote.extension_attributes = quote.extension_attributes || {};
            quote.extension_attributes.polkurier_point = point;
        },
        getSelectedPoint: function (methodCode) {
            return this.selectedPoints[methodCode]();
        },
        setSelectedPoint: function (point, methodCode) {
            for (const allowedMethod of this.allowedMethods) {
                this.selectedPoints[allowedMethod](null);
            }
            this.selectedPoints[methodCode](point);
            this.selectedPoint(point);
        },
        getPointInfo: function (methodCode) {
            const point = this.getSelectedPoint(methodCode);
            if (!point) {
                return $t('No point selected');
            }
            return '[' + point.id + '] ' + point.address;
        }
    });
});
