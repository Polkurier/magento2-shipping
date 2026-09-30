/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The pack templates beside the form. Choosing one sets the last parcel to it, or opens
 * a new parcel of it in the parcel editor when there's none; the parcels list shows the result.
 */
define([
    'ko',
    'uiRegistry',
    './part'
], function (ko, registry, Part) {
    'use strict';

    return Part.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/workspace/templates',
            // The parcels part, from the form's XML.
            parcels: ''
        },

        initState: function () {
            this.items = ko.observableArray([]);
            this.active = ko.observable('');
            this.hasDefault = ko.observable(true);
            this.settingsUrl = ko.observable('');
        },

        refresh: function () {
            const templates = this.get('templates') || [],
                packs = (this.get('packs') || []).filter(function (pack) {
                    return pack && !pack.delete;
                }),
                last = packs[packs.length - 1];

            this.items(templates);
            this.active(last && last.template_id ? String(last.template_id) : '');
            this.hasDefault(templates.some(function (template) {
                return template.is_default;
            }));
            this.settingsUrl(this.get('urls.pack_templates') || '');
        },

        /**
         * @param {Object} template
         */
        choose: function (template) {
            registry.get(this.parcels, function (parcels) {
                parcels.applyTemplate(template, false);
            });
        },

        addCustom: function () {
            registry.get(this.parcels, function (parcels) {
                parcels.addCustom();
            });
        },

        /**
         * @param {Object} template
         * @param {KeyboardEvent} event
         * @returns {Boolean}
         */
        onKey: function (template, event) {
            if (event.key === 'Enter' || event.key === ' ') {
                if (template) {
                    this.choose(template);
                } else {
                    this.addCustom();
                }

                return false;
            }

            return true;
        }
    });
});
