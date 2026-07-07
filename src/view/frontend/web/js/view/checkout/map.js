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

        initialize() {
            this._super();
            this.selectedPoint = ko.observable();
            this.selectedPoints = {};
            for (const allowedMethod of this.allowedMethods) {
                this.selectedPoints[allowedMethod] = ko.observable();
            }
        },

        hasModalButton(methodCode) {
            return this.allowedMethods.includes(methodCode);
        },

        openModalWindow(methodCode) {
            const self = this,
                selectedPoint = this.getSelectedPoint(methodCode);
            if (selectedPoint) {
                this.initMap(methodCode, selectedPoint.latitude, selectedPoint.longitude);
            } else if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function (position) {
                    this.initMap(methodCode, position.coords.latitude, position.coords.longitude);
                }.bind(this), function () {
                    this.initMap(methodCode, this.mapDefaultLatitude, this.mapDefaultLongitude);
                }.bind(this))
            } else {
                this.initMap(methodCode, this.mapDefaultLatitude, this.mapDefaultLongitude);
            }
        },

        initMap(methodCode, latitude, longitude) {
            const self = this;
            const map = new POLKURIER.PointsMap({
                token: this.mapToken,
                baseUrl: this.mapBaseUrl,
                providers: this.methodProviders[methodCode],
                functions: this.mapRequiredPointFunctions,
                latitude: latitude,
                longitude: longitude,
                zoom: this.mapDefaultZoom,
                showList: this.mapShowList,
                showSearchInput: this.mapShowSearchInput
            });
            map.showMapDialog();
            map.onConfirm(function (point) {
                this.updateQuote(point, methodCode);
                this.setSelectedPoint(point, methodCode);
                map.closeMapDialog();
            }.bind(this));
        },

        updateQuote(point, methodCode) {
            quote.extensionAttributes = quote.extensionAttributes || {};
            quote.extensionAttributes.polkurier_point = quote.extensionAttributes.polkurier_point || {};
            quote.extensionAttributes.polkurier_point[methodCode] = point;
        },

        getSelectedPoint(methodCode) {
            return this.selectedPoints[methodCode]();
        },

        setSelectedPoint(point, methodCode) {
            this.selectedPoints[methodCode](point);
            this.selectedPoint(point);
        },

        getPointInfo(methodCode) {
            const point = this.getSelectedPoint(methodCode);
            if (!point) {
                return $t('No point selected');
            }
            return '[' + point.id + '] ' + point.address;
        }
    });
});
