/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Polkurier options of a shipping label on Magento's New Shipment page.
 *
 * Every group of options is a one-line summary with its own modal. The values live in hidden
 * fields of the shipment form, which Magento sends along when it asks the carrier for the label.
 * Changing the carrier drops what the new one does not support.
 */
define([
    'jquery',
    'mage/translate',
    'Polkurier_Shipping/js/shipment/points-map',
    'Magento_Ui/js/modal/modal'
], function ($, $t, pointsMap) {
    'use strict';

    return function (config, element) {
        const root = $(element),
            values = $.extend({services: []}, config.values),
            summary = root.find('[data-role=summary]'),
            inputs = root.find('[data-role=inputs]');

        values.services = (values.services || []).filter(Boolean);

        function carrier(code) {
            return config.carriers[code === undefined ? values.carrier_code : code] || null;
        }

        function optionLabel(field, value) {
            const option = (config.options[field] || []).find(function (item) {
                return String(item.value) === String(value);
            });

            return option ? option.label : '';
        }

        function money(amount) {
            return Number(amount).toFixed(2) + ' ' + config.currency;
        }

        function filled(value) {
            return value !== null && value !== undefined && String(value).trim() !== '';
        }

        /**
         * Drops what the chosen carrier does not support, and adds insurance where the carrier
         * insists on it with cash on delivery.
         */
        function normalize() {
            const c = carrier();

            if (!c) {
                return;
            }
            if (c.shipment_types.length && c.shipment_types.indexOf(values.shipment_type) < 0) {
                values.shipment_type = c.shipment_types[0];
            }
            values.services = values.services.filter(function (service) {
                return c.services.indexOf(service) >= 0;
            });
            if (c.point === 'NONE') {
                values.recipient_point_id = '';
            }
            if (!c.cod) {
                values.cod_amount = '';
            }
            if (!c.insurance) {
                values.insurance_amount = '';
            } else if (c.insurance_with_cod && filled(values.cod_amount) && !filled(values.insurance_amount)) {
                values.insurance_amount = values.cod_amount;
            }
            if (values.pickup_mode === 'drop_off' && !c.drop_off) {
                values.pickup_mode = 'courier';
            }
            if (!c.pickup_date) {
                values.pickup_date = values.pickup_time_from = values.pickup_time_to = '';
            }
        }

        const sections = [
            {
                title: $t('Carrier'),
                summary: function () {
                    const parts = [optionLabel('carrier_code', values.carrier_code) || $t('Not chosen')];

                    if (filled(values.shipment_type)) {
                        parts.push(optionLabel('shipment_type', values.shipment_type));
                    }
                    parts.push($t('from %1').replace('%1', optionLabel('sender_address_id', values.sender_address_id || '')));

                    return parts.join(' · ');
                },
                fields: [
                    {name: 'carrier_code', label: $t('Carrier'), type: 'select'},
                    {
                        name: 'shipment_type',
                        label: $t('Shipment type'),
                        type: 'select',
                        filter: function (value, form) {
                            const c = carrier(form.find('[name=carrier_code]').val());

                            return !c || !c.shipment_types.length || c.shipment_types.indexOf(value) >= 0;
                        },
                        dependsOn: 'carrier_code'
                    },
                    {name: 'sender_address_id', label: $t('Sender'), type: 'select'},
                    {name: 'description', label: $t('Description'), type: 'text', maxlength: 30}
                ]
            },
            {
                title: $t('Pickup point'),
                visible: function () {
                    return carrier() && carrier().point !== 'NONE';
                },
                warning: function () {
                    return carrier() && carrier().point === 'REQUIRED' && !filled(values.recipient_point_id);
                },
                summary: function () {
                    if (filled(values.recipient_point_id)) {
                        return values.recipient_point_id;
                    }

                    return carrier().point === 'REQUIRED' ? $t('Required, not chosen') : $t('None');
                },
                fields: [
                    {name: 'recipient_point_id', label: $t('Point code'), type: 'text', map: true}
                ]
            },
            {
                title: $t('Cash on delivery'),
                visible: function () {
                    return carrier() && carrier().cod;
                },
                summary: function () {
                    if (!filled(values.cod_amount)) {
                        return $t('None');
                    }

                    return [
                        money(values.cod_amount),
                        optionLabel('cod_type', values.cod_type),
                        optionLabel('cod_bank_account_id', values.cod_bank_account_id)
                    ].filter(Boolean).join(' · ');
                },
                fields: [
                    {name: 'cod_amount', label: $t('Amount'), type: 'number', note: $t('Leave empty for none. Order total: %1').replace('%1', money(config.grandTotal))},
                    {name: 'cod_type', label: $t('Collection type'), type: 'select'},
                    {name: 'cod_return_type', label: $t('Paid out'), type: 'select'},
                    {name: 'cod_bank_account_id', label: $t('Bank account'), type: 'select'}
                ]
            },
            {
                title: $t('Insurance and services'),
                summary: function () {
                    const parts = [];

                    if (carrier() && carrier().insurance) {
                        parts.push(filled(values.insurance_amount)
                            ? $t('Insured for %1').replace('%1', money(values.insurance_amount))
                            : $t('Not insured'));
                    }
                    values.services.forEach(function (service) {
                        parts.push(optionLabel('services', service));
                    });

                    return parts.length ? parts.join(' · ') : $t('None');
                },
                fields: [
                    {
                        name: 'insurance_amount',
                        label: $t('Insurance amount'),
                        type: 'number',
                        note: $t('Leave empty for none.'),
                        visible: function () {
                            return carrier() && carrier().insurance;
                        }
                    },
                    {
                        name: 'services',
                        label: $t('Additional services'),
                        type: 'checkboxes',
                        filter: function (value) {
                            return carrier() && carrier().services.indexOf(value) >= 0;
                        }
                    }
                ]
            },
            {
                title: $t('Handover'),
                warning: function () {
                    const c = carrier();

                    return Boolean(c && values.pickup_mode === 'courier' && (
                        (c.pickup_date_required && !filled(values.pickup_date))
                        || (c.pickup_time_required && !(filled(values.pickup_time_from) && filled(values.pickup_time_to)))
                    ));
                },
                summary: function () {
                    let text = optionLabel('pickup_mode', values.pickup_mode);

                    if (values.pickup_mode === 'courier' && filled(values.pickup_date)) {
                        text += ', ' + values.pickup_date;
                        if (filled(values.pickup_time_from) && filled(values.pickup_time_to)) {
                            text += ' ' + values.pickup_time_from + '–' + values.pickup_time_to;
                        }
                    } else if (values.pickup_mode === 'courier' && carrier() && carrier().pickup_date_required) {
                        text += ', ' + $t('collection date required');
                    }

                    return text;
                },
                fields: [
                    {
                        name: 'pickup_mode',
                        label: $t('Handover'),
                        type: 'select',
                        filter: function (value) {
                            return value !== 'drop_off' || (carrier() && carrier().drop_off);
                        }
                    },
                    {name: 'pickup_date', label: $t('Collection date'), type: 'date', visible: pickupDateVisible, shownFor: courierPickup},
                    {name: 'pickup_time_from', label: $t('From'), type: 'time', visible: pickupDateVisible, shownFor: courierPickup},
                    {name: 'pickup_time_to', label: $t('To'), type: 'time', visible: pickupDateVisible, shownFor: courierPickup}
                ]
            }
        ];

        function pickupDateVisible() {
            return carrier() && carrier().pickup_date;
        }

        /**
         * A collection date only means something when a courier collects the parcels.
         *
         * @param {jQuery} form the modal's form
         * @returns {Boolean}
         */
        function courierPickup(form) {
            return form.find('[name=pickup_mode]').val() === 'courier';
        }

        function buildControl(field, form) {
            const id = 'polkurier-label-' + field.name,
                wrapper = $('<div class="admin__field"/>'),
                control = $('<div class="admin__field-control"/>');

            wrapper.append($('<label class="admin__field-label"/>').attr('for', id).append($('<span/>').text(field.label)));
            wrapper.append(control);

            if (field.type === 'select') {
                const select = $('<select class="admin__control-select"/>').attr({id: id, name: field.name}),
                    fill = function () {
                        const current = select.val() !== null ? select.val() : String(values[field.name] || '');

                        select.empty();
                        (config.options[field.name] || []).forEach(function (option) {
                            if (!field.filter || field.filter(String(option.value), form)) {
                                select.append($('<option/>').val(option.value).text(option.label));
                            }
                        });
                        select.val(current);
                        if (select.val() === null) {
                            select.prop('selectedIndex', 0);
                        }
                    };

                select.val(String(values[field.name] || ''));
                fill();
                select.val(String(values[field.name] || ''));
                if (select.val() === null) {
                    select.prop('selectedIndex', 0);
                }
                if (field.dependsOn) {
                    form.on('change', '[name=' + field.dependsOn + ']', fill);
                }
                control.append(select);
            } else if (field.type === 'checkboxes') {
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
            } else {
                const input = $('<input class="admin__control-text"/>').attr({
                    id: id,
                    name: field.name,
                    type: field.type,
                    step: field.type === 'number' ? '0.01' : null,
                    min: field.type === 'number' ? '0' : null,
                    maxlength: field.maxlength || null
                }).val(filled(values[field.name]) ? values[field.name] : '');

                control.append(input);
                if (field.map && config.map && config.map.token && carrier() && carrier().providers && carrier().providers.length) {
                    control.addClass('polkurier-label-options__point');
                    control.append($('<button type="button" class="action-secondary polkurier-label-options__map"/>')
                        .text($t('Choose on map'))
                        .on('click', function () {
                            pointsMap.open(config.map, carrier().providers, config.mapSearch || '', function (pointId) {
                                input.val(pointId).trigger('change');
                            });
                        }));
                }
            }
            if (field.shownFor) {
                const update = function () {
                    wrapper.toggle(field.shownFor(form));
                };

                form.on('change', 'select', update);
                // The form is filled field by field; decide once every field is in it.
                setTimeout(update, 0);
            }
            if (field.note) {
                control.append($('<div class="admin__field-note"/>').text(field.note));
            }

            return wrapper;
        }

        function openModal(section) {
            const form = $('<form class="admin__fieldset polkurier-label-options__form"/>');

            section.fields.forEach(function (field) {
                if (!field.visible || field.visible()) {
                    form.append(buildControl(field, form));
                }
            });
            form.on('submit', function (event) {
                event.preventDefault();
            });

            form.modal({
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
                        const carrierBefore = values.carrier_code;

                        section.fields.forEach(function (field) {
                            if (field.type === 'checkboxes') {
                                if (form.find('[name=' + field.name + ']').length) {
                                    values[field.name] = form.find('[name=' + field.name + ']:checked').map(function () {
                                        return this.value;
                                    }).get();
                                }
                            } else if (form.find('[name=' + field.name + ']').length) {
                                values[field.name] = form.find('[name=' + field.name + ']').val();
                            }
                        });
                        if (carrierBefore !== values.carrier_code || section.fields[0].name === 'cod_amount') {
                            normalize();
                        }
                        render();
                        this.closeModal();
                    }
                }],
                closed: function () {
                    form.modal('destroy').remove();
                }
            });
            form.modal('openModal');
        }

        function render() {
            summary.empty();
            sections.forEach(function (section) {
                if (section.visible && !section.visible()) {
                    return;
                }
                const text = $('<span/>').text(section.summary()),
                    edit = $('<button type="button" class="action-default polkurier-label-options__edit"/>').text($t('Change'));

                edit.on('click', function () {
                    openModal(section);
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
                        .val(filled(values[name]) ? values[name] : ''));
                }
            });
        }

        function toggle() {
            const on = $('#create_shipping_label').is(':checked');

            root.find('[data-role=on]').toggle(on);
            root.find('[data-role=off]').toggle(!on);
        }

        /**
         * Magento's packaging popup starts every package in pounds and inches; Polkurier carriers
         * work in kilograms and centimetres. Packages are copied from this template.
         */
        function metricPackages() {
            // The attribute, not the value: a copied select keeps what its markup says.
            [['container_weight_units', 'KILOGRAM'], ['container_dimension_units', 'CENTIMETER']].forEach(function (unit) {
                const options = $('#package_template select[name=' + unit[0] + '] option');

                options.removeAttr('selected').prop('selected', false);
                options.filter('[value=' + unit[1] + ']').attr('selected', 'selected').prop('selected', true);
            });
        }

        /**
         * Pack templates fill in a package: its parcel type, size and weight, which stay editable.
         * They're offered next to the package type, never as one. Every package is copied from
         * Magento's package template, so the choice is added there once.
         */
        function addTemplateChoice() {
            const templates = config.templates || [],
                title = $('#package_template .admin__page-section-title .title');

            if (!templates.length || !title.length || $('#package_template .polkurier-pack-template').length) {
                return;
            }
            const select = $('<select class="admin__control-select polkurier-pack-template"/>')
                .attr('aria-label', $t('Pack template'))
                .append($('<option value=""/>').text($t('Pack template…')));

            templates.forEach(function (template) {
                select.append($('<option/>').val(template.id).text(template.name));
            });
            title.after(select);
        }

        function applyTemplate(select) {
            const template = (config.templates || []).find(function (item) {
                    return item.id === select.value;
                }),
                pack = $(select).closest('[id^=package_block]'),
                set = function (name, value) {
                    pack.find('[name=' + name + ']').val(value).trigger('change');
                };

            if (!template) {
                return;
            }
            // Units first: Magento converts the values when they change.
            pack.find('select[name=container_weight_units]').val('KILOGRAM');
            pack.find('select[name=container_dimension_units]').val('CENTIMETER');
            set('package_container', template.pack_type);
            set('container_length', template.length);
            set('container_width', template.width);
            set('container_height', template.height);
            set('container_weight', template.weight);
        }

        $(document).on('change', '#packages_content select.polkurier-pack-template', function () {
            applyTemplate(this);
        });
        $(document).on('change', '#create_shipping_label', toggle);
        render();
        toggle();
        metricPackages();
        addTemplateChoice();
    };
});
