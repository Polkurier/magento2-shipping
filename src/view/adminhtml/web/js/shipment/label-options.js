/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Polkurier options of a shipping label on Magento's New Shipment page.
 *
 * Every group of options is a one-line summary with its own modal. The values live in hidden
 * fields of the shipment form, which Magento sends along when it asks the carrier for the label.
 * Changing the carrier drops what the new one does not support. "Create Shipping Label" starts
 * ticked, so the shipment is created with its Polkurier order.
 *
 * When the order ships in parts, cash on delivery starts from what the order's other Polkurier
 * orders don't collect yet, and the insurance follows the quantities shipped until it's changed
 * by hand.
 *
 * The rules are in ./label-options/state, the groups in ./sections, their modals in ./modal and
 * the packaging popup's part in ./packaging.
 */
define([
    'jquery',
    'underscore',
    'mage/translate',
    './label-options/state',
    './label-options/sections',
    './label-options/modal',
    './label-options/packaging'
], function ($, _, $t, State, Sections, Modal, packaging) {
    'use strict';

    return function (config, element) {
        const root = $(element),
            summary = root.find('[data-role=summary]'),
            inputs = root.find('[data-role=inputs]'),
            state = State(config),
            sections = Sections(state, config),
            modal = Modal(state, config),
            values = state.values;

        function render() {
            summary.empty();
            sections.forEach(function (section) {
                if (section.visible && !section.visible()) {
                    return;
                }
                const text = $('<span/>').text(section.summary()),
                    edit = $('<button type="button" class="action-default polkurier-label-options__edit"/>').text($t('Change'));

                edit.on('click', function () {
                    modal.open(section, function () {
                        render();
                        followInsurance();
                    });
                });
                summary.append($('<dt/>').text(section.title));
                summary.append($('<dd/>')
                    .toggleClass('polkurier-label-options__warning', Boolean(section.warning && section.warning()))
                    .append(text, edit));
            });

            inputs.empty();
            config.fields.forEach(function (name) {
                if (name === 'services') {
                    inputs.append($('<input type="hidden"/>').attr('name', config.name + '[services][]').val(''));
                    values.services.forEach(function (service) {
                        inputs.append($('<input type="hidden"/>').attr('name', config.name + '[services][]').val(service));
                    });
                } else {
                    inputs.append($('<input type="hidden"/>').attr('name', config.name + '[' + name + ']')
                        .val(state.filled(values[name]) ? values[name] : ''));
                }
            });
        }

        function followInsurance() {
            if (state.followInsurance()) {
                render();
            }
        }

        function toggle() {
            const on = $('#create_shipping_label').is(':checked');

            root.find('[data-role=on]').toggle(on);
            root.find('[data-role=off]').toggle(!on);
        }

        $(document).on('change', '#create_shipping_label', toggle);
        // Redrawn once typing pauses, not with every key.
        $(document).on('change keyup', state.ITEM_INPUT, _.debounce(followInsurance, 150));
        // A shipment of a Polkurier order goes with a Polkurier order: the label is on by default.
        // Clicked, not set, so Magento's own handler runs too.
        if ($('#create_shipping_label').length && !$('#create_shipping_label').is(':checked')) {
            $('#create_shipping_label')[0].click();
        }
        render();
        followInsurance();
        toggle();
        packaging.init(config.templates || [], config.parcelWeight || 'products');
    };
});
