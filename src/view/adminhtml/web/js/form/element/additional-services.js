define([
    'Magento_Ui/js/form/element/checkbox-set',
    'uiRegistry',
    'ko'
], function (CheckboxSet, registry, ko) {
    'use strict';

    return CheckboxSet.extend({
        configStatus: {},
        configOverview: {},
        defaults: {
            template: 'Polkurier_Shipping/form/element/additional-services',
            configurable: {},
            listens: {
                value: 'servicesChanged'
            }
        },

        initialize() {
            this._super();
            this.previous = [];
            for (let service in this.configurable) {
                this.configStatus[service] = {
                    valid: ko.observable(true),
                    dirty: ko.observable(false)
                };
                this.configOverview[service] = ko.observable();
            }

            return this;
        },

        isConfigValid(service) {
            return this.configStatus[service] ? this.configStatus[service].valid() : true;
        },

        setConfigValid(service, valid) {
            if (this.configStatus[service]) {
                this.configStatus[service].valid(valid);
            }
        },

        getConfigOverview(service) {
            return this.configurable[service].overviewFormat
                ? this.configOverview[service]()
                : '';
        },

        setConfigOverview(service, fieldset) {
            if (this.configurable[service].overviewFormat === undefined) {
                return;
            }
            const overview = this.configurable[service].overviewFormat.replace(
                /\{\$([a-zA-Z_]\w*)\}/g,
                function (match, name) {
                    return this.getFieldValue(fieldset, name);
                }.bind(this)
            );

            this.configOverview[service](overview);
        },

        getFieldValue(fieldset, fieldName) {
            const field = fieldset.elems().find((element) => element.name.indexOf(fieldName) !== -1);
            switch (field.formElement) {
                case 'select':
                    return field.getOption(field.value()).label;
                case 'input':
                default:
                    return field.value();
            }
        },

        servicesChanged(values) {
            values = values || [];
            registry.async('index = polkurier_modal_manager')(function (manager) {
                values.forEach(function (service) {
                    if (this.previous.indexOf(service) === -1 && this.configStatus[service].dirty() === false) {
                        this.configStatus[service].dirty(true);
                        manager.configure(this.configurable[service].fieldset, service, this.setConfigOverview.bind(this));
                    }
                }, this);
                this.previous = values.slice();
            }.bind(this));
        },

        isSelected(service) {
            return (this.value() || []).indexOf(service) !== -1;
        },

        isConfigurable(service) {
            return this.configurable[service] !== undefined;
        },

        isConfigurableOverview(service) {
            return this.isConfigurable(service) && this.configurable[service].overviewFormat !== undefined;
        },

        configure(service) {
            registry.async('index = polkurier_modal_manager')(function (manager) {
                manager.configure(this.configurable[service].fieldset, service, this.setConfigOverview.bind(this));
            }.bind(this));
        }
    });
});
