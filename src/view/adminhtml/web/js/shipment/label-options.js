/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Polkurier options of a shipping label on Magento's New Shipment page.
 *
 * Every group of options is a one-line summary, edited in place with its Change button. The values
 * live in hidden fields of the shipment form, which Magento sends along when it asks the carrier
 * for the label. Changing the carrier drops what the new one does not support. With "Create
 * Shipping Label" ticked, the label places the Polkurier order; without it, the shipment is saved
 * without one, as Magento saves it, and gets its Polkurier order later from its own page.
 *
 * When the order ships in parts, cash on delivery follows the quantities shipped: their worth, or,
 * for the shipment carrying the rest, everything left to collect. The insurance follows them too.
 * Either stops following once it's changed by hand.
 *
 * The rules are in ./label-options/state, the groups in ./sections and their editing in ./editor;
 * the packaging popup's part is ./label-options/packaging, set up for the page by ./packaging-setup.
 */
define([
    'jquery',
    'underscore',
    'mage/translate',
    './label-options/state',
    './label-options/sections',
    './label-options/editor',
    'Magento_Ui/js/modal/alert'
], function ($, _, $t, State, Sections, Editor, alert) {
    'use strict';

    return function (config, element) {
        const root = $(element),
            summary = root.find('[data-role=summary]'),
            inputs = root.find('[data-role=inputs]'),
            state = State(config),
            sections = Sections(state, config),
            editor = Editor(state, config),
            values = state.values;
        // The group being edited in place, one at a time.
        let editing = null;

        function render() {
            summary.empty();
            sections.forEach(function (section) {
                if (section.visible && !section.visible()) {
                    return;
                }
                if (editing === section.title) {
                    const form = editor.edit(section, function () {
                        editing = null;
                        render();
                        followQuantities();
                    }, function () {
                        editing = null;
                        render();
                    });

                    summary.append($('<dt class="polkurier-label-options__editing"/>').text(section.title));
                    summary.append($('<dd class="polkurier-label-options__editing"/>').append(form));
                    form.find('input, select').filter(':visible').first().trigger('focus');

                    return;
                }
                const text = $('<span/>').text(section.summary()),
                    edit = $('<button type="button" class="action-default polkurier-label-options__edit"/>')
                        .text($t('Change'));

                // Any other open group closes without applying.
                edit.on('click', function () {
                    editing = section.title;
                    render();
                });
                // Say what's wrong: red alone would leave it to guesswork.
                const why = [].concat(section.warning ? section.warning() || [] : []).filter(Boolean),
                    line = $('<dd/>')
                        .toggleClass('polkurier-label-options__warning', why.length > 0)
                        .append(text, edit);

                why.forEach(function (reason) {
                    line.append($('<div class="polkurier-label-options__why"/>').text(reason));
                });
                summary.append($('<dt/>').text(section.title));
                summary.append(line);
            });

            inputs.empty();
            config.fields.forEach(function (name) {
                if (name === 'services') {
                    inputs.append($('<input type="hidden"/>').attr('name', config.name + '[services][]').val(''));
                    values.services.forEach(function (service) {
                        inputs.append(
                            $('<input type="hidden"/>').attr('name', config.name + '[services][]').val(service)
                        );
                    });
                } else {
                    inputs.append($('<input type="hidden"/>').attr('name', config.name + '[' + name + ']')
                        .val(state.filled(values[name]) ? values[name] : ''));
                }
            });
        }

        /**
         * The cash on delivery first: the insurance may have to cover it.
         */
        function followQuantities() {
            const cod = state.followCod(),
                insurance = state.followInsurance();

            if (cod || insurance) {
                render();
            }
        }

        function toggle() {
            const on = config.existingShipment || $('#create_shipping_label').is(':checked');

            root.find('[data-role=on]').toggle(on);
            root.find('[data-role=off]').toggle(!on);
        }

        $(document).on('change', '#create_shipping_label', toggle);
        // Redrawn once typing pauses, not with every key.
        $(document).on('change keyup', state.ITEM_INPUT, _.debounce(followQuantities, 150));
        /**
         * Sends the options with the label request, which on a shipment's own page has no shipment
         * form to take them from.
         */
        function sendWithLabelRequest() {
            const prototype = window.Packaging && window.Packaging.prototype,
                original = prototype && prototype.sendCreateLabelRequest;

            if (typeof original !== 'function' || original.polkurier) {
                return;
            }
            prototype.sendCreateLabelRequest = function () {
                const params = this.paramsCreateLabelRequest;

                inputs.find('input').each(function () {
                    if (/\[]$/.test(this.name)) {
                        params[this.name] = [].concat(
                            Array.isArray(params[this.name]) ? params[this.name] : [],
                            this.value
                        );
                    } else {
                        params[this.name] = this.value;
                    }
                });

                return original.apply(this, arguments);
            };
            prototype.sendCreateLabelRequest.polkurier = true;
        }

        /**
         * Keeps Magento from opening the packaging window, and from asking for the label, while
         * the options aren't right: it says what to fix instead.
         */
        function blockWhileWrong() {
            const prototype = window.Packaging && window.Packaging.prototype;

            ['showWindow', 'sendCreateLabelRequest'].forEach(function (method) {
                const original = prototype && prototype[method];

                if (typeof original !== 'function' || original.polkurierChecked) {
                    return;
                }
                prototype[method] = function () {
                    const problems = state.problems(),
                        // The packages are there once the label is asked for.
                        packages = method === 'sendCreateLabelRequest' && !problems.length
                            ? state.packageProblems(
                                $('#packages_content select[name=package_container]').map(function () {
                                    return this.value;
                                }).get()
                            )
                            : [],
                        list = $('<ul/>');

                    if (!problems.length && !packages.length) {
                        return original.apply(this, arguments);
                    }
                    problems.concat(packages).forEach(function (problem) {
                        list.append($('<li/>').text(problem));
                    });
                    if (packages.length) {
                        alert({
                            title: $t('These packages can\'t go'),
                            content: $('<div/>').append(
                                $('<p/>').text($t('Change the packages, or the carrier in "Polkurier shipping label":')),
                                list
                            ).html()
                        });

                        return undefined;
                    }
                    render();
                    element.scrollIntoView({block: 'center'});
                    alert({
                        title: $t('The Polkurier shipping label isn\'t ready'),
                        content: $('<div/>').append(
                            $('<p/>').text($t('Fix these in "Polkurier shipping label" first:')),
                            list
                        ).html()
                    });

                    return undefined;
                };
                prototype[method].polkurierChecked = true;
            });
        }

        blockWhileWrong();
        if (config.existingShipment) {
            sendWithLabelRequest();
        }
        render();
        followQuantities();
        toggle();
    };
});
