/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The parcels of the shipment: a list with a way to add, change and remove each, and a parcel
 * editor. A new parcel opens in the editor, empty or filled in from a pack template chosen in this
 * list or beside the form, so its size, weight and the shipped items it holds can be set before
 * it's added. Until it's typed in, its weight follows the parcel weight setting: what the items in
 * it weigh, or its template's weight (rules.parcelWeight).
 */
define([
    'jquery',
    'ko',
    'underscore',
    'mage/translate',
    'Magento_Ui/js/modal/confirm',
    'Magento_Ui/js/modal/alert',
    './part',
    './rules',
    'Polkurier_Shipping/js/request',
    'Magento_Ui/js/modal/modal'
], function ($, ko, _, $t, confirm, alert, Part, rules, request) {
    'use strict';

    // Every parcel is one parcel: its amount is always 1.
    const FIELDS = ['length', 'width', 'height', 'weight'];

    return Part.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/workspace/parcels'
        },

        initState: function () {
            this.rows = ko.observableArray([]);
            this.templates = ko.observableArray([]);
            this.packTypes = ko.observableArray([]);
            this.editor = {
                index: -1,
                templateId: null,
                // What the editor was filled in with, to tell a parcel changed by hand.
                origin: {},
                // The weight of the template the parcel came from, null without one.
                templateWeight: null,
                // A template of another shipment type: the parcels there are give way to this one.
                shipmentType: null,
                packType: ko.observable(''),
                length: ko.observable(''),
                width: ko.observable(''),
                height: ko.observable(''),
                weight: ko.observable(''),
                items: ko.observableArray([]),
                contentsWeight: ko.observable(0),
                error: ko.observable('')
            };
            // The weight the editor last filled in from the contents: anything else was typed.
            this.followedWeight = null;
            this.unpacked = ko.observable('');
            this.canPack = ko.observable(false);
            this.estimated = ko.observable(false);
            this.packing = ko.observable(false);
            this.editorModal = null;
            this.chooserModal = null;
        },

        refresh: function () {
            const choices = (this.get('choices') || {}).pack_type || {},
                items = rules.itemsById(this.get() || {}),
                describe = this.describeContents.bind(this);

            this.templates(this.get('templates') || []);
            this.packTypes(_.map(choices, function (label, value) {
                return {value: value, label: label};
            }));
            this.rows(this.packs().map(function (pack, index) {
                return {
                    index: index,
                    name: choices[pack.pack_type] || pack.pack_type || $t('Parcel'),
                    size: $t('%1 × %2 × %3 cm, %4 kg')
                        .replace('%1', pack.length || '?')
                        .replace('%2', pack.width || '?')
                        .replace('%3', pack.height || '?')
                        .replace('%4', pack.weight || '?'),
                    contents: describe(pack, items),
                    incomplete: FIELDS.some(function (field) {
                        return rules.amount(pack[field]) <= 0;
                    })
                };
            }));
            this.unpacked(this.describeUnpacked(items));
            this.estimated(Boolean(this.get('packs_estimated')) && this.packs().length > 0);
            this.canPack(this.get('can_ship') !== false && _.some(this.get('items') || [], function (item) {
                return rules.amount(item.qty) > 0;
            }));
        },

        /**
         * Puts the items shipped into parcels of the pack templates, from the products' shipping
         * dimensions, in place of the parcels there are, once confirmed.
         */
        packAutomatically: function () {
            const pack = function () {
                this.packing(true);
                request().post(this.get('urls.pack'), {
                    data: {order_id: this.get('order_id'), items: this.get('items'), shipment_type: this.get('shipment_type')}
                }, {
                    done: function (response) {
                        if (response.error) {
                            alert({title: $t('Pack automatically'), content: _.escape(response.message)});
                        } else {
                            this.set('packs_estimated', true);
                            this.store(response.packs);
                            if (response.message) {
                                alert({title: $t('Pack automatically'), content: _.escape(response.message)});
                            }
                        }
                    }.bind(this),
                    fail: function () {
                        alert({title: $t('Pack automatically'), content: $t('The items could not be packed.')});
                    },
                    always: function () {
                        this.packing(false);
                    }.bind(this)
                });
            }.bind(this);

            if (!this.packs().length) {
                pack();

                return;
            }
            confirm({
                title: $t('Pack automatically?'),
                content: $t('The parcels there are give way to parcels of your pack templates, with the items spread over them by their size.')
                    + ' ' + $t('It\'s an estimate: every product counts as a box of its dimensions, whatever its real shape.'),
                actions: {confirm: pack}
            });
        },

        /**
         * What one parcel holds and weighs, in one line; empty when not said.
         *
         * @param {Object} pack
         * @param {Object} items the shipped items by order item id
         * @returns {String}
         */
        describeContents: function (pack, items) {
            const parts = (pack.items || []).filter(function (row) {
                    return rules.amount(row.qty) > 0 && items[String(row.order_item_id)];
                }).map(function (row) {
                    return rules.amount(row.qty) + ' × ' + items[String(row.order_item_id)].name;
                }),
                weight = rules.contentsWeight(pack, items);

            if (!parts.length) {
                return '';
            }

            return parts.join(', ') + (weight > 0 ? ' · ' + $t('%1 kg').replace('%1', String(weight)) : '');
        },

        /**
         * The shipped items no parcel holds yet, once any parcel says what it holds.
         *
         * @param {Object} items the shipped items by order item id
         * @returns {String}
         */
        describeUnpacked: function (items) {
            const packs = this.packs(),
                totals = rules.packed(packs),
                left = [];

            if (this.get('can_ship') === false || !Object.keys(totals).length) {
                return '';
            }
            Object.keys(items).forEach(function (id) {
                const qty = rules.amount(items[id].qty) - (totals[id] || 0);

                if (qty > 0.0001) {
                    left.push(Math.round(qty * 1000) / 1000 + ' × ' + items[id].name);
                }
            });

            return left.length ? $t('Not in any parcel yet: %1').replace('%1', left.join(', ')) : '';
        },

        /**
         * The form's parcels, without the rows Magento's dynamic rows used to mark as deleted.
         *
         * @returns {Array<Object>}
         */
        packs: function () {
            return _.filter(_.values(this.get('packs') || []), function (pack) {
                return pack && !pack.delete;
            }).map(function (pack) {
                return _.pick(pack, 'template_id', 'pack_type', 'length', 'width', 'height', 'weight', 'amount', 'items');
            });
        },

        /**
         * @param {Array<Object>} packs
         */
        store: function (packs) {
            this.set('packs', packs);
        },

        /**
         * The id of the template the last parcel came from, for the template list to mark.
         *
         * @returns {String}
         */
        activeTemplateId: function () {
            const packs = this.packs(),
                last = packs[packs.length - 1];

            return last && last.template_id ? String(last.template_id) : '';
        },

        add: function () {
            if (this.templates().length) {
                this.chooserModal.modal('openModal');
            } else {
                this.addCustom();
            }
        },

        /**
         * Enter or Space on a chooser entry does what a click does.
         *
         * @param {Object|null} template null for a parcel without a template
         * @param {KeyboardEvent} event
         * @returns {Boolean} whether the key goes on to the browser
         */
        onChooserKey: function (template, event) {
            if (event.key !== 'Enter' && event.key !== ' ') {
                return true;
            }
            if (template) {
                this.applyTemplate(template, true);
            } else {
                this.addCustom();
            }

            return false;
        },

        addCustom: function () {
            const weight = rules.amount(this.get('order_weight'));

            this.closeChooser();
            // Starts from what the order's products weigh, when their weight is known.
            this.openEditor(-1, {pack_type: 'ST', weight: weight > 0 ? weight : ''});
        },

        /**
         * @param {Object} row
         */
        edit: function (row) {
            this.openEditor(row.index, this.packs()[row.index] || {});
        },

        /**
         * @param {Object} row
         */
        remove: function (row) {
            confirm({
                title: $t('Remove the parcel?'),
                content: _.escape(row.name + ' (' + row.size + ')'),
                actions: {
                    confirm: function () {
                        const packs = this.packs();

                        packs.splice(row.index, 1);
                        this.store(packs);
                    }.bind(this)
                }
            });
        },

        /**
         * Starts a parcel from a template. A new parcel, asked for or when there's none yet, opens
         * in the editor filled in from the template; otherwise the last parcel takes the template's
         * type, size and weight. A template of another shipment type replaces the parcels there
         * are, once confirmed, since one shipment can't mix types.
         *
         * @param {Object} template
         * @param {Boolean} [asNew]
         */
        applyTemplate: function (template, asNew) {
            const packs = this.packs(),
                adding = asNew || !packs.length,
                otherType = Boolean(template.shipment_type) && template.shipment_type !== this.get('shipment_type'),
                pack = {
                    template_id: template.id,
                    pack_type: template.pack_type,
                    length: template.length,
                    width: template.width,
                    height: template.height,
                    weight: template.weight,
                    amount: 1
                },
                apply = function (replace) {
                    if (adding) {
                        this.openEditor(-1, pack, otherType ? template.shipment_type : null);

                        return;
                    }
                    if (replace) {
                        this.store([pack]);
                    } else {
                        // The parcel keeps what it holds; only its size and weight change.
                        pack.items = packs[packs.length - 1].items || [];
                        pack.weight = this.weigh(template.weight, pack.items);
                        packs[packs.length - 1] = pack;
                        this.store(packs);
                    }
                    if (template.shipment_type) {
                        this.set('shipment_type', template.shipment_type);
                    }
                }.bind(this);

            this.closeChooser();
            if (otherType && packs.length > (adding ? 0 : 1)) {
                confirm({
                    title: $t('Change the shipment type?'),
                    content: $t('This template is for another shipment type than the parcels you have. Changing the type removes them.'),
                    actions: {
                        confirm: function () {
                            apply(true);
                        }
                    }
                });

                return;
            }
            apply(false);
        },

        /**
         * @param {Number} index -1 for a new parcel
         * @param {Object} pack
         * @param {String|null} [shipmentType] the type a new parcel changes the shipment to, which
         *     the parcels there are give way to
         */
        openEditor: function (index, pack, shipmentType) {
            const editor = this.editor;

            editor.index = index;
            editor.templateId = pack.template_id || null;
            editor.templateWeight = this.templateWeight(editor.templateId);
            editor.origin = pack;
            editor.shipmentType = shipmentType || null;
            editor.packType(pack.pack_type || 'ST');
            FIELDS.forEach(function (field) {
                editor[field](pack[field] === undefined || pack[field] === null ? '' : String(pack[field]));
            });
            editor.items(this.editorItems(index, pack));
            editor.contentsWeight(this.weighContents());
            // A weight other than the setting's of a parcel that says what it holds was typed in.
            this.followedWeight = (pack.items || []).length && rules.amount(editor.weight()) !== this.editorWeight()
                ? null
                : editor.weight();
            editor.error('');
            this.editorModal.modal('openModal');
        },

        /**
         * The shipped items for the editor, each with what this parcel holds of it and how many are
         * left for it, besides what the other parcels hold.
         *
         * @param {Number} index the parcel edited, -1 for a new one
         * @param {Object} pack
         * @returns {Array<Object>}
         */
        editorItems: function (index, pack) {
            const data = this.get() || {},
                // Parcels of another shipment type give way to this one, so hold nothing.
                others = rules.packed((this.editor.shipmentType ? [] : this.packs()).filter(function (other, position) {
                    return position !== index;
                })),
                held = {};

            if (data.can_ship === false) {
                return [];
            }
            (pack.items || []).forEach(function (row) {
                held[String(row.order_item_id)] = rules.amount(row.qty);
            });

            return (data.items || []).filter(function (item) {
                return rules.amount(item.qty) > 0;
            }).map(function (item) {
                const id = String(item.order_item_id),
                    left = Math.max(0, rules.amount(item.qty) - (others[id] || 0)),
                    row = {
                        id: id,
                        name: item.name,
                        weight: rules.amount(item.weight),
                        left: left,
                        leftText: $t('of %1 left').replace('%1', String(Math.round(left * 1000) / 1000)),
                        qty: ko.observable(held[id] ? String(held[id]) : '')
                    };

                row.qty.subscribe(this.followContents, this);

                return row;
            }, this);
        },

        /**
         * @returns {Number} what the editor's items weigh, in kilograms
         */
        weighContents: function () {
            return Math.round(this.editor.items().reduce(function (total, row) {
                return total + row.weight * rules.amount(row.qty());
            }, 0) * 1000) / 1000;
        },

        /**
         * What a parcel of the template given, holding the items given, weighs by the setting.
         *
         * @param {Number|String|null} templateWeight
         * @param {Array<Object>} rows the parcel's items: order_item_id and qty
         * @returns {Number}
         */
        weigh: function (templateWeight, rows) {
            const items = rules.itemsById(this.get() || {});

            return rules.parcelWeight(this.get('parcel_weight') || 'products', rules.amount(templateWeight) || null, (rows || []).map(function (row) {
                const item = items[String(row.order_item_id)];

                return {weight: item ? rules.amount(item.weight) : 0, qty: rules.amount(row.qty)};
            }));
        },

        /**
         * @param {String|Number|null} templateId
         * @returns {Number|null} the weight of that pack template, null when there's none
         */
        templateWeight: function (templateId) {
            const template = templateId ? this.templates().find(function (candidate) {
                return String(candidate.id) === String(templateId);
            }) : null;

            return template ? rules.amount(template.weight) : null;
        },

        /**
         * @returns {Number} what the parcel in the editor weighs by the setting, 0 when unknown
         */
        editorWeight: function () {
            return this.weigh(this.editor.templateWeight, this.editor.items().map(function (row) {
                return {order_item_id: row.id, qty: row.qty()};
            }));
        },

        /**
         * Keeps the weight at what the setting says, unless it was typed in.
         */
        followContents: function () {
            const editor = this.editor,
                weight = this.editorWeight();

            editor.contentsWeight(this.weighContents());
            if (weight > 0 && this.followedWeight !== null && String(editor.weight()) === String(this.followedWeight)) {
                this.followedWeight = String(weight);
                editor.weight(this.followedWeight);
            }
        },

        saveEditor: function () {
            const editor = this.editor,
                packs = editor.shipmentType ? [] : this.packs(),
                previous = editor.origin,
                pack = {pack_type: editor.packType()};

            if (FIELDS.some(function (field) {
                return rules.amount(editor[field]()) <= 0;
            })) {
                editor.error($t('Enter the size and weight, each greater than zero.'));

                return;
            }
            ['length', 'width', 'height'].forEach(function (field) {
                pack[field] = Math.round(rules.amount(editor[field]()));
            });
            pack.amount = 1;
            pack.weight = rules.amount(editor.weight());
            pack.items = [];
            const over = editor.items().find(function (row) {
                const qty = rules.amount(row.qty());

                if (qty > 0) {
                    pack.items.push({order_item_id: row.id, qty: qty});
                }

                return qty < 0 || qty > row.left + 0.0001;
            });

            if (over) {
                editor.error($t('The parcels would hold more of "%1" than the shipment carries.').replace('%1', over.name));

                return;
            }
            // A parcel of another type or size no longer is the template it came from; its weight
            // may be what's in it.
            pack.template_id = editor.templateId && ['pack_type', 'length', 'width', 'height'].every(function (field) {
                return String(previous[field]) === String(pack[field]);
            }) ? editor.templateId : null;
            if (editor.index >= 0) {
                packs[editor.index] = pack;
            } else {
                packs.push(pack);
            }
            this.store(packs);
            if (editor.shipmentType) {
                this.set('shipment_type', editor.shipmentType);
            }
            this.editorModal.modal('closeModal');
        },

        closeChooser: function () {
            if (this.chooserModal) {
                this.chooserModal.modal('closeModal');
            }
        },

        /**
         * @param {HTMLElement} element
         */
        initEditor: function (element) {
            this.editorModal = $(element).modal({
                type: 'popup',
                title: $t('Parcel'),
                modalClass: 'polkurier-workspace-modal polkurier-parcel-modal',
                buttons: [{
                    text: $t('Cancel'),
                    class: 'action-secondary',
                    click: function () {
                        this.closeModal();
                    }
                }, {
                    text: $t('Confirm'),
                    class: 'action-primary',
                    click: this.saveEditor.bind(this)
                }]
            });
        },

        /**
         * @param {HTMLElement} element
         */
        initChooser: function (element) {
            this.chooserModal = $(element).modal({
                type: 'popup',
                title: $t('Add a parcel'),
                modalClass: 'polkurier-workspace-modal',
                buttons: []
            });
        }
    });
});
