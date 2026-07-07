define([
    'uiComponent',
    'uiRegistry',
    'jquery',
    'mage/translate',
    'Magento_Ui/js/modal/modal'
], function (Component, registry, $, $t, modal) {
    'use strict';

    return Component.extend({
        initialize() {
            this._super();
            this.modalElement = $('<div/>');
            this.placeholder = null;
            this.currentElement = null;

            $('body').append(this.modalElement);

            modal({
                type: 'popup',
                responsive: true,
                innerScroll: true,
                buttons: [{
                    text: $t('Done'),
                    class: 'action-primary',
                    click: function () {
                        this.closeModal();
                    }
                }]
            }, this.modalElement);

            return this;
        },

        configure(fieldsetName, value, callback) {
            registry.async('index = ' + fieldsetName)(function (fieldset) {
                const element = fieldset.element
                    ? $(fieldset.element)
                    : $('[data-index="' + fieldset.index + '"]');
                if (!element.length) {
                    return;
                }
                this.open(fieldset, element, value, callback);
            }.bind(this));
        },

        open(fieldset, element, value, callback) {
            if (this.currentElement) {
                this.restore();
            }
            this.currentElement = element;
            this.placeholder = $('<span class="pk-modal-placeholder"/>');
            element.before(this.placeholder);
            this.modalElement.empty();
            this.modalElement.modal('option', 'title', fieldset.label);
            this.modalElement.append(element.show());
            this.modalElement.modal('openModal');
            this.modalElement.one('modalclosed', function () {
                this.validateComponent(fieldset);
                if (typeof callback === 'function') {
                    callback(value, fieldset);
                }
                this.restore();
            }.bind(this));
        },

        restore() {
            if (!this.currentElement || !this.placeholder) {
                return;
            }
            this.placeholder.replaceWith(this.currentElement);
            this.currentElement.hide();
            this.currentElement = null;
            this.placeholder = null;
        },

        validateComponent(component) {
            let valid = true;

            if (typeof component.validate === 'function') {
                valid = component.validate().valid;
            }

            if (component.elems) {
                component.elems.each(function (child) {
                    valid = this.validateComponent(child) && valid;
                }.bind(this));
            }

            return valid;
        }
    });
});
