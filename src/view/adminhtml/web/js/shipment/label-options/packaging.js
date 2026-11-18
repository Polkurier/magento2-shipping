/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * Magento's packaging popup, made fit for Polkurier: packages in kilograms and centimetres, pack
 * templates to fill one in, package weights by the parcel weight setting, and the items packed
 * into the pack templates automatically, as the Polkurier shipment screen packs them.
 */
define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/confirm',
    'Polkurier_Shipping/js/request',
    'Polkurier_Shipping/js/shipment/workspace/rules',
    'Magento_Shipping/order/packaging'
], function ($, $t, confirm, request, rules) {
    'use strict';

    /**
     * The popup starts every package in pounds and inches; Polkurier carriers work in kilograms
     * and centimetres. Packages are copied from this template.
     */
    function switchPackagesToMetric() {
        // The attribute, not the value: a copied select keeps what its markup says.
        [['container_weight_units', 'KILOGRAM'], ['container_dimension_units', 'CENTIMETER']].forEach(function (unit) {
            const options = $('#package_template select[name=' + unit[0] + '] option');

            options.removeAttr('selected').prop('selected', false);
            options.filter('[value=' + unit[1] + ']').attr('selected', 'selected').prop('selected', true);
        });
    }

    /**
     * Pack templates fill in a package: its parcel type, size and weight, which stay editable.
     * They're offered in a column of their own before the package type, never as a package type,
     * with the default template preselected. Every package is copied from Magento's package
     * template, so the column is added there once.
     *
     * @param {Array<Object>} templates
     */
    function addTemplateChoice(templates) {
        const table = $('#package_template table').first(),
            type = table.find('th.col-type, td.col-type');

        if (!templates.length || type.length !== 2 || table.find('.col-polkurier-template').length) {
            return;
        }
        const select = $('<select class="admin__control-select polkurier-pack-template"/>')
            .attr('aria-label', $t('Pack template'))
            .append($('<option value=""/>').text($t('No template')));

        templates.forEach(function (template) {
            const option = $('<option/>').val(template.id).text(template.name);

            // The attribute, not the property: a copied select keeps what its markup says.
            if (template.is_default) {
                option.attr('selected', 'selected');
            }
            select.append(option);
        });
        type.first().before($('<th class="col-polkurier-template"/>').text($t('Pack template')));
        type.last().before($('<td class="col-polkurier-template"/>').append(select, keepChoice()));
    }

    /**
     * Under "No template": an option to save the package as a pack template, under a name, once
     * the shipment is placed. It's copied with the package, as the template choice is.
     *
     * @returns {jQuery}
     */
    function keepChoice() {
        return $('<div class="polkurier-pack-keep" style="display: none"/>').append(
            $('<label class="admin__field-option"/>').append(
                $('<input type="checkbox" class="admin__control-checkbox polkurier-pack-keep__box"/>'),
                $('<span/>').text(' ' + $t('Save as a pack template'))
            ),
            $('<input type="text" class="admin__control-text polkurier-pack-keep__name" style="display: none"/>')
                .attr({placeholder: $t('Its type and size'), 'aria-label': $t('Saved as')})
        );
    }

    /**
     * Shows the "Save as a pack template" choice for a package without a template, and its name
     * field once ticked.
     *
     * @param {jQuery} pack a package block
     */
    function toggleKeep(pack) {
        const keep = pack.find('.polkurier-pack-keep'),
            none = !pack.find('select.polkurier-pack-template').val();

        keep.toggle(none);
        keep.find('.polkurier-pack-keep__name').toggle(none && keep.find('.polkurier-pack-keep__box').prop('checked'));
    }

    /**
     * Sends the packages to save as pack templates with the label request: each package's params
     * get the name to save it under (Polkurier\Shipping\Model\Shipment\Native\NativeLabelPackageMapper::KEEP_AS_PARAM),
     * or its type and size when no name was given.
     */
    function sendKeptPackages() {
        const prototype = window.Packaging && window.Packaging.prototype,
            original = prototype && prototype.sendCreateLabelRequest;

        if (typeof original !== 'function' || original.polkurierKeep) {
            return;
        }
        prototype.sendCreateLabelRequest = function () {
            const params = this.paramsCreateLabelRequest;

            Object.keys(params).forEach(function (key) {
                if (/\[polkurier_keep_as]$/.test(key)) {
                    delete params[key];
                }
            });
            this.packagesContent.childElements().each(function (element) {
                const pack = $(element),
                    name = (pack.find('.polkurier-pack-keep__name').val() || '').trim(),
                    size = ['container_length', 'container_width', 'container_height'].map(function (field) {
                        return pack.find('[name=' + field + ']').val();
                    }).join(' × ');

                if (!pack.find('select.polkurier-pack-template').val()
                    && pack.find('.polkurier-pack-keep__box').prop('checked')
                ) {
                    params['packages[' + this.getPackageId(element) + '][params][polkurier_keep_as]'] = name
                        || pack.find('select[name=package_container] option:selected').text().trim()
                            + ' ' + size + ' cm';
                }
            }.bind(this));

            return original.apply(this, arguments);
        };
        prototype.sendCreateLabelRequest.polkurierKeep = true;
    }

    /**
     * Packages the window made before this ran, on a page still loading: they get the template
     * choice, the default template and the units, as a new package does.
     *
     * @param {Array<Object>} templates
     * @param {String} mode
     */
    function catchUp(templates, mode) {
        const column = $('#package_template table').first().find('.col-polkurier-template');

        if (column.length !== 2) {
            return;
        }
        $('#packages_content [id^=package_block]').each(function () {
            const pack = $(this),
                type = pack.find('table').first().find('th.col-type, td.col-type');

            if (pack.find('.col-polkurier-template').length || type.length !== 2) {
                return;
            }
            type.first().before(column.first().clone());
            type.last().before(column.last().clone());
            pack.find('select[name=container_weight_units]').val('KILOGRAM');
            pack.find('select[name=container_dimension_units]').val('CENTIMETER');
            const select = pack.find('select.polkurier-pack-template')[0];

            if (select && select.value) {
                applyTemplate(select, templates, mode);
            }
            toggleKeep(pack);
        });
    }

    /**
     * A new package takes its chosen template, the default one, straight away.
     *
     * @param {Array<Object>} templates
     * @param {String} mode
     */
    function applyOnNewPackage(templates, mode) {
        const prototype = window.Packaging && window.Packaging.prototype,
            original = prototype && prototype.newPackage;

        if (typeof original !== 'function' || original.polkurier) {
            return;
        }
        prototype.newPackage = function () {
            original.apply(this, arguments);
            // Magento puts the new package on top.
            const block = $('#packages_content [id^=package_block]').first(),
                select = block.find('select.polkurier-pack-template')[0];

            if (select && select.value) {
                applyTemplate(select, templates, mode);
            }
            toggleKeep(block);
        };
        prototype.newPackage.polkurier = true;
    }

    /**
     * Weighs a package by the parcel weight setting: either what its products weigh, or its pack
     * template's weight. A package holding a product without a weight takes the template's weight
     * either way. Without a template, Magento's sum of the products stays, but never as "NaN" for a
     * product without a weight.
     *
     * @param {jQuery} pack a package block
     * @param {Array<Object>} templates
     * @param {String} mode
     */
    function weighPackage(pack, templates, mode) {
        const chosen = pack.find('select.polkurier-pack-template').val(),
            template = templates.find(function (item) {
                return item.id === chosen;
            }),
            field = pack.find('[name=container_weight]'),
            contents = pack.find('.package_items .grid tbody tr').map(function () {
                return {
                    weight: rules.parseAmount($(this).find('[data-role=item-weight]').text()),
                    qty: rules.parseAmount($(this).find('[name=qty]').val())
                };
            }).get(),
            weight = rules.calculateParcelWeight(
                mode,
                template ? rules.parseAmount(template.weight) : null,
                contents,
                template ? template.tare : null
            );

        if (template || isNaN(parseFloat(field.val()))) {
            field.val(weight > 0 ? weight : '');
        }
    }

    /**
     * Magento sets a package's weight to what its products weigh whenever they change; the
     * package is weighed again by the setting right after.
     *
     * @param {Array<Object>} templates
     * @param {String} mode
     */
    function followParcelWeight(templates, mode) {
        const prototype = window.Packaging && window.Packaging.prototype,
            original = prototype && prototype._recalcContainerWeightAndCustomsValue;

        if (typeof original !== 'function' || original.polkurier) {
            return;
        }
        prototype._recalcContainerWeightAndCustomsValue = function (container) {
            original.apply(this, arguments);
            weighPackage($(container).closest('[id^=package_block]'), templates, mode);
        };
        prototype._recalcContainerWeightAndCustomsValue.polkurier = true;
    }

    /**
     * @param {HTMLSelectElement} select a package's pack template choice
     * @param {Array<Object>} templates
     * @param {String} mode
     */
    function applyTemplate(select, templates, mode) {
        const template = templates.find(function (item) {
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
        weighPackage(pack, templates, mode);
    }

    /**
     * What the window says about packing automatically, above the packages.
     *
     * @param {Array<String>} lines none to say nothing
     * @param {Boolean} [error]
     */
    function note(lines, error) {
        let box = $('#packaging_window .polkurier-packaging-note');

        if (!box.length) {
            box = $('<div class="message polkurier-packaging-note"/>').insertBefore('#packages_content');
        }
        box.toggleClass('message-notice notice', !error).toggleClass('message-error error', Boolean(error))
            .empty().toggle(lines.length > 0);
        lines.forEach(function (line) {
            box.append($('<div/>').text(line));
        });
    }

    /**
     * The items the window packs, as Magento offers them to a package: the quantity shipped of
     * each, by order item id. Requested once per packing.
     *
     * @param {Object} popup the packaging window's Packaging
     * @param {Function} done called with the grid's markup and the quantities
     * @param {Function} fail
     */
    function loadShippedItems(popup, done, fail) {
        new Ajax.Request(popup.itemsGridUrl, {
            parameters: {'shipment_id': popup.shipmentId},
            onSuccess: function (transport) {
                const html = transport.responseText || '',
                    holder = new Element('div'),
                    packed = popup.packages,
                    qtys = {};

                holder.update(html);
                // As offered to a popup without packages: everything shipped.
                popup.packages = [];
                popup.processPackagePrepare(holder);
                popup.packages = packed;
                holder.select('.grid tbody tr').each(function (row) {
                    const qty = parseFloat(row.select('[name="qty"]')[0].value);

                    if (qty > 0) {
                        qtys[row.select('[type="checkbox"]')[0].value] = qty;
                    }
                });
                done(html, qtys);
            },
            onFailure: fail
        });
    }

    /**
     * Makes one package of a parcel packed automatically: its template, type, size and weight,
     * and its items, packed the way Magento packs the ones chosen in a package.
     *
     * @param {Object} popup the packaging popup's Packaging
     * @param {Object} pack template_id, pack_type, length, width, height, weight, items
     * @param {String} html the items' grid
     * @param {Number} number the package's number, which keeps its grid's ids unique
     * @param {Array<Object>} templates
     * @param {String} mode
     */
    function fillPackage(popup, pack, html, number, templates, mode) {
        popup.newPackage();
        // Magento puts the new package on top.
        const block = $('#packages_content [id^=package_block]').first(),
            element = block[0],
            select = block.find('select.polkurier-pack-template')[0],
            template = templates.find(function (item) {
                return item.id === String(pack.template_id || '');
            }),
            set = function (name, value) {
                block.find('[name=' + name + ']').val(value).trigger('change');
            },
            contents = {},
            grid = element.select('[data-role=package-items] .grid_prepare')[0],
            // Magento asks for the grid for each package, each with ids of its own.
            gridId = (html.match(/packaging-data-grid-(\d+)/) || [])[1];

        if (select) {
            select.value = template ? template.id : '';
        }
        toggleKeep(block);
        if (template) {
            applyTemplate(select, templates, mode);
        } else {
            block.find('select[name=container_weight_units]').val('KILOGRAM');
            block.find('select[name=container_dimension_units]').val('CENTIMETER');
        }
        // A parcel of its own size, or a template's: as packed.
        set('package_container', pack.pack_type);
        set('container_length', pack.length);
        set('container_width', pack.width);
        set('container_height', pack.height);
        (pack.items || []).forEach(function (row) {
            contents[String(row.order_item_id)] = rules.parseAmount(row.qty);
        });
        grid.update(gridId
            ? html.replace(
                new RegExp('(packaging-data-grid-|select-items?-)' + gridId + '\\b', 'g'),
                '$1' + gridId + '_' + number
            )
            : html);
        popup.processPackagePrepare(grid);
        grid.select('.grid tbody tr').each(function (row) {
            const checkbox = row.select('[type="checkbox"]')[0],
                qty = contents[checkbox.value] || 0;

            checkbox.checked = qty > 0;
            if (qty > 0) {
                row.select('[name="qty"]')[0].value = qty;
            }
            popup._observeQty.call(checkbox);
        });
        popup.packItems(element.select('[data-action=package-save-items]')[0]);
        if (rules.parseAmount(pack.weight) > 0) {
            block.find('[name=container_weight]').val(pack.weight);
        }
    }

    /**
     * Packs the shipped items into packages of the pack templates, by their shipping dimensions,
     * replacing the existing packages. Packages that hold items keep the shipment to their kind;
     * without any, the items decide what they're sent as
     * (Polkurier\Shipping\Model\Shipment\Packing\AutoPacker::packDraft()).
     *
     * @param {Object} popup the packaging popup's Packaging
     * @param {Object} config url, orderId, shipmentId
     * @param {Array<Object>} templates
     * @param {String} mode
     */
    function packAutomatically(popup, config, templates, mode) {
        const kinds = $('#packages_content [id^=package_block]').filter(function () {
                return $(this).find('.package_items .grid tbody tr').length > 0;
            }).map(function () {
                return {pack_type: $(this).find('select[name=package_container]').val()};
            }).get(),
            stop = function () {
                $('body').trigger('processStop');
            },
            failed = function () {
                stop();
                note([$t('The items could not be packed.')], true);
            };

        $('body').trigger('processStart');
        loadShippedItems(popup, function (html, qtys) {
            request().post(config.url, {
                data: {
                    order_id: config.orderId,
                    target_shipment_id: config.shipmentId || '',
                    items: Object.keys(qtys).map(function (id) {
                        return {order_item_id: id, qty: qtys[id]};
                    }),
                    packs: kinds
                }
            }, {
                done: function (response) {
                    stop();
                    if (!response || response.error) {
                        note(
                            [response && response.message ? response.message : $t('The items could not be packed.')],
                            true
                        );

                        return;
                    }
                    popup.cleanPackages();
                    response.packs.forEach(function (pack, index) {
                        fillPackage(popup, pack, html, index + 1, templates, mode);
                    });
                    note([$t('Packed automatically. Check the actual parcels\' and items\' sizes and weights before placing the order.')]
                        .concat(response.message ? [response.message] : []));
                },
                fail: failed
            });
        }, failed);
    }

    /**
     * Adds a "Pack automatically" button next to Add Package. Where new shipments start packed, the
     * window also packs the items when it opens without packages. Emptying the packages (Cancel)
     * removes the note.
     *
     * @param {Object} config url, orderId, shipmentId, onOpen
     * @param {Array<Object>} templates
     * @param {String} mode
     */
    function offerAutoPack(config, templates, mode) {
        const prototype = window.Packaging && window.Packaging.prototype,
            show = prototype && prototype.showWindow,
            clean = prototype && prototype.cleanPackages;

        if (typeof show !== 'function' || show.polkurier) {
            return;
        }
        prototype.showWindow = function () {
            const empty = !this.packagesContent.childElements().length,
                packaging = this;

            show.apply(this, arguments);
            const add = $('#packaging_window').closest('.modal-inner-wrap').find('[data-action=add-packages]');

            if (add.length && !add.siblings('[data-action=polkurier-pack]').length) {
                $('<button type="button" class="action-secondary" data-action="polkurier-pack"/>')
                    .append($('<span/>').text($t('Pack automatically')))
                    .insertAfter(add)
                    .on('click', function () {
                        const packed = $('#packages_content .package_items .grid tbody tr').length > 0;

                        confirm({
                            title: $t('Pack automatically?'),
                            content: packed
                                ? $t('The current parcels are replaced with parcels of your pack templates, with the items spread over them by their size.')
                                : $t('The items are spread over parcels of your pack templates by their size.'),
                            actions: {
                                confirm: function () {
                                    packAutomatically(window.packaging || packaging, config, templates, mode);
                                }
                            }
                        });
                    });
            }
            // Only once it's open: the label options keep it shut while they're wrong.
            if (empty && config.onOpen && $('#packaging_window').closest('.modal-slide').hasClass('_show')) {
                packAutomatically(this, config, templates, mode);
            }
        };
        prototype.showWindow.polkurier = true;
        prototype.cleanPackages = function () {
            clean.apply(this, arguments);
            note([]);
        };
    }

    return {
        /**
         * Called once per page, by ../packaging-setup.
         *
         * @param {Array<Object>} templates the pack templates with a size
         * @param {String} mode the parcel weight setting: 'products' or 'template'
         * @param {Object|null} autoPack where the items are packed automatically, null where not
         */
        init: function (templates, mode, autoPack) {
            $(document).on('change', '#packages_content select.polkurier-pack-template', function () {
                applyTemplate(this, templates, mode);
                toggleKeep($(this).closest('[id^=package_block]'));
            });
            $(document).on('change', '#packages_content .polkurier-pack-keep__box', function () {
                toggleKeep($(this).closest('[id^=package_block]'));
            });
            sendKeptPackages();
            followParcelWeight(templates, mode);
            applyOnNewPackage(templates, mode);
            if (autoPack) {
                offerAutoPack(autoPack, templates, mode);
            }
            switchPackagesToMetric();
            addTemplateChoice(templates);
            catchUp(templates, mode);
        }
    };
});
