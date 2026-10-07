/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Polkurier options of a shipping label on Magento's New Shipment page.
 *
 * Every group of options is a one-line summary, edited in place with its Change. The values live in hidden
 * fields of the shipment form, which Magento sends along when it asks the carrier for the label.
 * Changing the carrier drops what the new one does not support. "Create Shipping Label" starts
 * ticked, so the shipment is created with its Polkurier order.
 *
 * When the order ships in parts, cash on delivery follows the quantities shipped: what they're
 * worth, or everything left to collect for the shipment carrying the rest. The insurance follows
 * them too. Either stops following once it's changed by hand.
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

                // Another group open gives way, unapplied.
                edit.on('click', function () {
                    editing = section.title;
                    render();
                });
                // What's wrong, said: red alone would leave it to be guessed.
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
        // A shipment of a Polkurier order goes with a Polkurier order: the label can't be left out.
        // Clicked, not set, so Magento's own handler runs too. Magento unticks it when the packaging
        // window is closed or the label refused, and draws the items part again (a fresh, unticked
        // box) as quantities change, so the box is hidden for good, ticked whenever it appears, and
        // again right before a submit. The server refuses a shipment without it all the same
        // (Plugin\Adminhtml\ShipmentSavePlugin).
        function keepLabel() {
            const box = document.getElementById('create_shipping_label');

            if (box && !box.checked) {
                box.click();
            }
        }

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
        } else {
            $('body').addClass('polkurier-label-kept');
            keepLabel();
            new MutationObserver(keepLabel).observe(document.body, {childList: true, subtree: true});
            // Captured on the way down: before the button's own handler opens the packaging window.
            document.addEventListener('click', function (event) {
                if ($(event.target).closest('.submit-button').length) {
                    keepLabel();
                }
            }, true);
        }
        render();
        followQuantities();
        toggle();
    };
});
