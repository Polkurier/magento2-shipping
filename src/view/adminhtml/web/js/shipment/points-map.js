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
 */
define(['mage/translate', 'Magento_Ui/js/modal/alert'], function ($t, alert) {
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
         * The point's address in one line, for showing next to its code.
         *
         * @param {Object} point as the map returns it
         * @returns {String}
         */
        describe: function (point) {
            if (!point) {
                return '';
            }

            return point.address || [point.street, [point.zip, point.city].filter(Boolean).join(' ')].filter(Boolean).join(', ');
        },

        /**
         * @param {{token: String, baseUrl: String}} map
         * @param {String[]} providers the carrier's point providers
         * @param {String} searchQuery where the map should start, e.g. "00-001 Warszawa"
         * @param {Function} onChoose called with the point's id and the point
         */
        open: function (map, providers, searchQuery, onChoose) {
            loadWidget(map.baseUrl).then(function (target) {
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
            }, function () {
                showFrame(false);
                alert({title: $t('Pickup point map'), content: $t('The pickup point map could not be loaded.')});
            });
        }
    };
});
