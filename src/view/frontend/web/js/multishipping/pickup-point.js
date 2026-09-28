/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * "Choose a pickup point" for one address of the multi-address checkout. Shown while a method that
 * delivers to a point is selected for that address; the point goes into a hidden field posted
 * with the shipping methods.
 */
define([
    'jquery',
    'Polkurier_Shipping/js/model/points-map',
    'mage/translate'
], function ($, pointsMap, $t) {
    'use strict';

    return function (config, element) {
        const root = $(element),
            form = root.closest('form'),
            radios = form.find('input[name="shipping_method[' + config.addressId + ']"]'),
            input = root.find('input[type="hidden"]'),
            label = root.find('[data-role="point-label"]');
        let chosen = config.selected;

        /**
         * @returns {String|null} the selected method's code when it delivers to a point
         */
        function selectedPointMethod() {
            const value = radios.filter(':checked').val() || '',
                prefix = config.carrierCode + '_',
                code = value.indexOf(prefix) === 0 ? value.substring(prefix.length) : null;

            return code !== null && config.methods[code] ? code : null;
        }

        function render() {
            const code = selectedPointMethod(),
                point = chosen && code && chosen.methodCode === code ? chosen : null;

            root.prop('hidden', code === null || !config.token);
            input.val(point ? point.id : '');
            label.text(point ? '[' + point.id + '] ' + point.label : $t('No pickup point chosen'));
        }

        radios.on('change', render);
        root.find('[data-role="choose-point"]').on('click', function () {
            const code = selectedPointMethod();

            if (code !== null) {
                pointsMap.open(config, config.methods[code].providers, config.searchQuery, function (point) {
                    chosen = {methodCode: code, id: point.id, label: point.label};
                    render();
                });
            }
        });
        form.on('submit', function (event) {
            const code = selectedPointMethod();

            if (code !== null && config.methods[code].required && !input.val()) {
                event.preventDefault();
                window.alert($t('Choose a pickup point for the selected delivery method.'));
            }
        });
        render();
    };
});
