/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The modal of one group of label options: its fields, filled in from the options chosen so far,
 * and Apply, which takes them back.
 */
define([
    'jquery',
    'mage/translate',
    'Polkurier_Shipping/js/shipment/points-map',
    'Magento_Ui/js/modal/modal'
], function ($, $t, pointsMap) {
    'use strict';

    /**
     * @param {Object} state see ./state
     * @param {Object} config the page's label options
     * @returns {{open: Function}}
     */
    return function (state, config) {
        const values = state.values,
            filled = state.filled;

        function select(field, form, id) {
            const control = $('<select class="admin__control-select"/>').attr({id: id, name: field.name}),
                fill = function () {
                    const current = control.val() !== null ? control.val() : String(values[field.name] || '');

                    control.empty();
                    (config.options[field.name] || []).forEach(function (option) {
                        if (!field.filter || field.filter(String(option.value), form)) {
                            control.append($('<option/>').val(option.value).text(option.label));
                        }
                    });
                    control.val(current);
                    if (control.val() === null) {
                        control.prop('selectedIndex', 0);
                    }
                };

            control.val(String(values[field.name] || ''));
            fill();
            control.val(String(values[field.name] || ''));
            if (control.val() === null) {
                control.prop('selectedIndex', 0);
            }
            if (field.dependsOn) {
                form.on('change.polkurierFields', '[name=' + field.dependsOn + ']', fill);
            }

            return control;
        }

        function checkboxes(field, form, id, control) {
            (config.options[field.name] || []).forEach(function (option) {
                if (field.filter && !field.filter(String(option.value), form)) {
                    return;
                }
                const boxId = id + '-' + option.value,
                    choice = $('<div class="admin__field admin__field-option"/>');

                choice.append($('<input type="checkbox" class="admin__control-checkbox"/>').attr({
                    id: boxId,
                    name: field.name,
                    value: option.value
                }).prop('checked', values.services.indexOf(String(option.value)) >= 0));
                choice.append($('<label class="admin__field-label"/>').attr('for', boxId).text(option.label));
                control.append(choice);
            });
            if (!control.children().length) {
                control.append($('<div class="admin__field-note"/>').text($t('This carrier offers no additional services.')));
            }
        }

        function input(field, id, control) {
            const box = $('<input class="admin__control-text"/>').attr({
                    id: id,
                    name: field.name,
                    type: field.type,
                    step: field.type === 'number' ? '0.01' : null,
                    min: field.type === 'number' ? '0' : null,
                    maxlength: field.maxlength || null
                }).val(filled(values[field.name]) ? values[field.name] : ''),
                carrier = state.carrier();

            control.append(box);
            if (field.map && config.map && config.map.token && carrier && carrier.providers && carrier.providers.length) {
                control.addClass('polkurier-label-options__point');
                control.append($('<button type="button" class="action-secondary polkurier-label-options__map"/>')
                    .text($t('Choose on map'))
                    .on('click', function () {
                        pointsMap.open(config.map, carrier.providers, config.mapSearch || '', function (pointId) {
                            box.val(pointId).trigger('change');
                        });
                    }));
            }
        }

        function field(definition, form) {
            const id = 'polkurier-label-' + definition.name,
                wrapper = $('<div class="admin__field"/>'),
                control = $('<div class="admin__field-control"/>');

            wrapper.append($('<label class="admin__field-label"/>').attr('for', id).append($('<span/>').text(definition.label)));
            wrapper.append(control);

            if (definition.type === 'select') {
                control.append(select(definition, form, id));
            } else if (definition.type === 'checkboxes') {
                checkboxes(definition, form, id, control);
            } else {
                input(definition, id, control);
            }
            if (definition.shownFor) {
                const update = function () {
                    wrapper.toggle(definition.shownFor(form));
                };

                form.on('change.polkurierFields', 'select', update);
                // The form is filled field by field; decide once every field is in it.
                setTimeout(update, 0);
            }
            if (definition.note) {
                control.append($('<div class="admin__field-note"/>')
                    .text(typeof definition.note === 'function' ? definition.note() : definition.note));
            }

            return wrapper;
        }

        /**
         * Takes the modal's fields back into the options chosen.
         *
         * @param {Object} section
         * @param {jQuery} form
         */
        function apply(section, form) {
            const carrierBefore = values.carrier_code,
                insuranceBefore = values.insurance_amount;

            section.fields.forEach(function (definition) {
                const fields = form.find('[name=' + definition.name + ']');

                if (!fields.length) {
                    return;
                }
                values[definition.name] = definition.type === 'checkboxes'
                    ? form.find('[name=' + definition.name + ']:checked').map(function () {
                        return this.value;
                    }).get()
                    : fields.val();
            });
            if (String(insuranceBefore || '') !== String(values.insurance_amount || '')) {
                state.insuranceSetByHand();
            }
            if (carrierBefore !== values.carrier_code || section.normalizes) {
                state.normalize();
            }
        }

        /**
         * One modal per group, built the first time it opens and refilled every time after, so
         * nothing is left behind however often it's opened.
         *
         * @type {Object<string, {form: jQuery, section: Object, applied: Function}>}
         */
        const modals = {};

        function create(section) {
            const entry = {form: $('<form class="admin__fieldset polkurier-label-options__form"/>')};

            entry.form.on('submit', function (event) {
                event.preventDefault();
            });
            entry.form.modal({
                title: section.title,
                type: 'popup',
                modalClass: 'polkurier-label-options__modal',
                buttons: [{
                    text: $t('Cancel'),
                    class: 'action-secondary',
                    click: function () {
                        this.closeModal();
                    }
                }, {
                    text: $t('Apply'),
                    class: 'action-primary',
                    click: function () {
                        apply(entry.section, entry.form);
                        entry.applied();
                        this.closeModal();
                    }
                }]
            });

            return entry;
        }

        return {
            /**
             * @param {Object} section one of ./sections
             * @param {Function} applied called once the fields are taken back
             */
            open: function (section, applied) {
                const entry = modals[section.title] = modals[section.title] || create(section);

                entry.section = section;
                entry.applied = applied;
                entry.form.off('.polkurierFields').empty();
                section.fields.forEach(function (definition) {
                    if (!definition.visible || definition.visible()) {
                        entry.form.append(field(definition, entry.form));
                    }
                });
                entry.form.modal('openModal');
            }
        };
    };
});
