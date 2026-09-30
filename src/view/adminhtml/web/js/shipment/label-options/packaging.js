/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Magento's packaging popup, made fit for Polkurier: packages in kilograms and centimetres, pack
 * templates to fill one in, and package weights by the parcel weight setting.
 */
define([
    'jquery',
    'mage/translate',
    'Polkurier_Shipping/js/shipment/workspace/rules',
    'Magento_Shipping/order/packaging'
], function ($, $t, rules) {
    'use strict';

    /**
     * The popup starts every package in pounds and inches; Polkurier carriers work in kilograms
     * and centimetres. Packages are copied from this template.
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
     *
     * @param {Array<Object>} templates
     */
    function addTemplateChoice(templates) {
        const title = $('#package_template .admin__page-section-title .title');

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

    /**
     * Weighs a package by the parcel weight setting: what the products in it weigh, or its pack
     * template's weight, which a package with a product without a weight takes either way. Without
     * a template, Magento's sum of the products stays, but never as "NaN" for a product without a
     * weight.
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
                    weight: rules.amount($(this).find('[data-role=item-weight]').text()),
                    qty: rules.amount($(this).find('[name=qty]').val())
                };
            }).get(),
            weight = rules.parcelWeight(mode, template ? rules.amount(template.weight) : null, contents);

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

    return {
        /**
         * @param {Array<Object>} templates the pack templates with a size
         * @param {String} mode the parcel weight setting: 'products' or 'template'
         */
        init: function (templates, mode) {
            $(document).on('change', '#packages_content select.polkurier-pack-template', function () {
                applyTemplate(this, templates, mode);
            });
            followParcelWeight(templates, mode);
            metricPackages();
            addTemplateChoice(templates);
        }
    };
});
