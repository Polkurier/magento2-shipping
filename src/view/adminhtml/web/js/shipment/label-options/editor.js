/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * Edits one group of label options in place, in its line of the summary. It shows the group's
 * fields, filled in from the options chosen so far, with Apply, which takes the values back, and
 * Cancel.
 */
define([
    'jquery',
    'mage/translate',
    'Polkurier_Shipping/js/shipment/points-map',
    'Polkurier_Shipping/js/request'
], function ($, $t, pointsMap, request) {
    'use strict';

    // The id of a form that doesn't exist: the fields belong to it, so they stay out of the
    // shipment's form around them.
    const OWN_FORM = 'polkurier-label-options-editor';

    /**
     * @param {Object} state see ./state
     * @param {Object} config the page's label options
     * @returns {{edit: Function}}
     */
    return function (state, config) {
        const values = state.values,
            filled = state.filled,
            // Only the answer to the latest request for collection dates counts.
            pickups = request();

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
                control.append(
                    $('<div class="admin__field-note"/>').text($t('This carrier offers no additional services.'))
                );
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
            if (field.map && pointsMap.canOpen(config.map)
                && carrier && carrier.providers && carrier.providers.length
            ) {
                control.addClass('polkurier-label-options__point');
                control.append($('<button type="button" class="action-secondary polkurier-label-options__map"/>')
                    .text($t('Choose on map'))
                    .on('click', function () {
                        // The recipient's postcode, or what the field says, e.g. the sender's.
                        const search = typeof field.mapSearch === 'function'
                            ? field.mapSearch(control.closest('.polkurier-label-options__form'))
                            : config.mapSearch;

                        pointsMap.open(config.map, carrier.providers, search || '', function (pointId, point) {
                            config.pointDetails = pointsMap.addPoint(config.pointDetails, pointId, point);
                            box.val(pointId).trigger('change');
                        }, field.mapFunctions);
                    }));
            }
        }

        /**
         * The collection date and window, chosen from those Polkurier offers for the carrier, the
         * sender and the recipient, as on the Polkurier shipment screen. What Polkurier doesn't
         * offer can't be picked, since the courier wouldn't come then.
         *
         * @param {String} id
         * @param {jQuery} control
         */
        function pickup(id, control) {
            const dates = $('<select class="admin__control-select" name="pickup_date"/>').attr('id', id),
                windows = $('<select class="admin__control-select" name="pickup_window"/>')
                    .attr('aria-label', $t('Collection time')),
                anyTime = $('<div class="admin__field-note"/>').text($t('Any time that day')),
                status = $('<div class="admin__field-note"/>'),
                setup = config.pickupTimes || {};
            let days = [];

            function fillWindows() {
                const day = days.find(function (candidate) {
                        return candidate.date === dates.val();
                    }),
                    current = (values.pickup_time_from || '') + '|' + (values.pickup_time_to || '');

                windows.empty();
                (day ? day.windows : []).forEach(function (hours) {
                    windows.append($('<option/>').val(hours.from + '|' + hours.to).text(hours.from + '–' + hours.to));
                });
                windows.val(current);
                if (windows.val() === null) {
                    windows.prop('selectedIndex', 0);
                }
                // A day without windows: the carrier comes at any time that day.
                windows.toggle(windows.children().length > 0);
                anyTime.toggle(Boolean(day) && windows.children().length === 0);
            }

            control.append(dates, windows, anyTime, status);
            dates.hide();
            windows.hide();
            anyTime.hide();
            dates.on('change', fillWindows);
            if (!filled(values.carrier_code) || !setup.url) {
                status.text($t('Choose a carrier to see its collection dates.'));

                return;
            }
            status.text($t('Reading the collection dates…'));
            pickups.post(setup.url, {data: {
                order_id: setup.orderId,
                carrier_code: values.carrier_code,
                sender_address_id: values.sender_address_id || '',
                new_sender_postcode: values.new_sender_postcode || '',
                recipient_postcode: setup.recipientPostcode || ''
            }}, {
                done: function (response) {
                    days = response.days || [];
                    status
                        .text(
                            response.error
                                ? response.message
                                : (days.length ? '' : $t('No collection dates available.'))
                        )
                        .toggleClass('polkurier-label-options__why', Boolean(response.error));
                    if (!days.length) {
                        return;
                    }
                    days.forEach(function (day) {
                        dates.append($('<option/>').val(day.date).text(day.date));
                    });
                    dates.val(values.pickup_date || '');
                    if (dates.val() === null) {
                        dates.prop('selectedIndex', 0);
                    }
                    dates.show();
                    fillWindows();
                },
                fail: function () {
                    status.text($t('The collection dates could not be read.')).addClass('polkurier-label-options__why');
                }
            });
        }

        function field(definition, form) {
            const id = 'polkurier-label-' + definition.name,
                wrapper = $('<div class="admin__field"/>'),
                control = $('<div class="admin__field-control"/>');

            wrapper.append(
                $('<label class="admin__field-label"/>').attr('for', id).append($('<span/>').text(definition.label))
            );
            wrapper.append(control);

            if (definition.type === 'pickup') {
                pickup(id, control);
            } else if (definition.type === 'checkbox') {
                // A single choice, with its label beside the box.
                wrapper.addClass('admin__field-option');
                wrapper.children('.admin__field-label').remove();
                control.append(
                    $('<input type="checkbox" class="admin__control-checkbox" value="1"/>')
                        .attr({id: id, name: definition.name})
                        .prop(
                            'checked',
                            filled(values[definition.name])
                                && String(values[definition.name]) !== '0'
                                && values[definition.name] !== false
                        ),
                    $('<label class="admin__field-label"/>').attr('for', id).text(definition.label)
                );
            } else if (definition.type === 'select') {
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

                form.on('change.polkurierFields', 'select, input[type=checkbox]', update);
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
         * Takes the group's fields back into the options chosen.
         *
         * @param {Object} section
         * @param {jQuery} form
         */
        function apply(section, form) {
            const senderBefore = values.sender_address_id,
                senderPointBefore = values.sender_point_id,
                carrierBefore = values.carrier_code,
                pickupModeBefore = values.pickup_mode,
                insuranceBefore = values.insurance_amount,
                codBefore = values.cod_amount,
                // The options start as the page gives them, numbers among them; the fields give strings.
                same = function (before, after) {
                    return String(before === null || before === undefined ? '' : before)
                        === String(after === null || after === undefined ? '' : after);
                };

            section.fields.forEach(function (definition) {
                const fields = form.find('[name=' + definition.name + ']');

                if (definition.type === 'pickup') {
                    // What was offered and picked; nothing when nothing was offered.
                    const slot = (form.find('[name=pickup_window]:visible').val() || '').split('|');

                    values.pickup_date = form.find('[name=pickup_date]:visible').val() || '';
                    values.pickup_time_from = values.pickup_date ? slot[0] || '' : '';
                    values.pickup_time_to = values.pickup_date ? slot[1] || '' : '';

                    return;
                }
                if (!fields.length) {
                    return;
                }
                if (definition.type === 'checkbox') {
                    values[definition.name] = fields.prop('checked') ? '1' : '';

                    return;
                }
                values[definition.name] = definition.type === 'checkboxes'
                    ? form.find('[name=' + definition.name + ']:checked').map(function () {
                        return this.value;
                    }).get()
                    : fields.val();
            });
            const senderOrCarrierChanged = !same(senderBefore, values.sender_address_id)
                || !same(carrierBefore, values.carrier_code);

            if (!same(insuranceBefore, values.insurance_amount)) {
                state.insuranceSetByHand();
            }
            if (!same(codBefore, values.cod_amount)) {
                state.codSetByHand();
            }
            // Collected from the sender: no drop-off point, which would show in the summary unused.
            if (!same(pickupModeBefore, values.pickup_mode) && values.pickup_mode !== 'drop_off') {
                values.sender_point_id = '';
            }
            if (!same(carrierBefore, values.carrier_code)) {
                state.swapPoint(carrierBefore, values.carrier_code);
            }
            // Another sender, or carrier, hands the parcels over at its own drop-off point,
            // unless one was just chosen.
            if (senderOrCarrierChanged && same(senderPointBefore, values.sender_point_id)) {
                values.sender_point_id = state.senderAddressPoint();
            }
            // Another sender or carrier starts from the default handover again.
            if (senderOrCarrierChanged) {
                values.pickup_mode = state.defaultHandover();
            }
            if (!same(carrierBefore, values.carrier_code) || section.normalizes) {
                state.normalize();
            }
        }

        return {
            /**
             * The group's fields, filled in from the options chosen so far, to edit in place, with
             * Apply, which takes them back, and Cancel, which leaves them as they were.
             *
             * @param {Object} section one of ./sections
             * @param {Function} applied called once the fields are taken back
             * @param {Function} cancelled
             * @returns {jQuery}
             */
            edit: function (section, applied, cancelled) {
                // Not a <form>: it sits inside the shipment's form. Its fields stay out of that
                // form, so they're neither sent with the shipment nor submit it.
                const form = $('<div class="admin__fieldset polkurier-label-options__form" role="group"/>')
                        .attr('aria-label', section.title),
                    actions = $('<div class="polkurier-label-options__actions"/>'),
                    done = function () {
                        apply(section, form);
                        applied();
                    };

                section.fields.forEach(function (definition) {
                    if (!definition.visible || definition.visible()) {
                        form.append(field(definition, form));
                    }
                });
                // A field named like its line doesn't show the name again; it keeps it for screen
                // readers.
                form.find('.admin__field > .admin__field-label').filter(function () {
                    return $(this).text().trim() === section.title;
                }).each(function () {
                    $('#' + $(this).attr('for')).attr('aria-label', section.title);
                    $(this).empty();
                });
                form.find('input, select, textarea, button').attr('form', OWN_FORM);
                // Enter in a field applies, Escape leaves it as it was.
                form.on('keydown', 'input', function (event) {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        done();
                    }
                });
                form.on('keydown', function (event) {
                    if (event.key === 'Escape') {
                        cancelled();
                    }
                });
                actions.append(
                    $('<button type="button" class="action-primary"/>')
                        .attr('form', OWN_FORM)
                        .text($t('Apply'))
                        .on('click', done),
                    $('<button type="button" class="action-tertiary"/>')
                        .attr('form', OWN_FORM)
                        .text($t('Cancel'))
                        .on('click', cancelled)
                );
                form.append(actions);

                return form;
            }
        };
    };
});
