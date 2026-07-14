define([
    'Magento_Ui/js/form/element/checkbox-set',
    'uiRegistry',
    'ko'
], function (CheckboxSet, registry, ko) {
    'use strict';

    return CheckboxSet.extend({
        servicesAvailability: {},
        defaults: {
            template: 'Polkurier_Shipping/form/element/additional-services',
            configurable: {},
            listens: {
                value: 'servicesChanged'
            },
            imports: {
                servicesAvailability: '${ $.provider }:data.services_availability'
            }
        },

        initialize() {
            this._super();
            this.previous = [];
            for (let service in this.configurable) {
                this.configurable[service].valid = ko.observable(true);
                this.configurable[service].dirty = ko.observable(false);
                this.configurable[service].overview = ko.observable();
                registry.async('index = ' + this.configurable[service].fieldset)(function (fieldset) {
                    this.configurable[service].fieldsetElement = fieldset;
                    this.setConfigOverview(service);
                }.bind(this));
            }

            return this;
        },

        isConfigValid(service) {
            return this.configurable[service] ? this.configurable[service].valid() : true;
        },

        setConfigValid(service, valid) {
            if (this.configurable[service]) {
                this.configurable[service].valid(valid);
            }
        },

        getConfigOverview(service) {
            return this.configurable[service].overviewFormat
                ? this.configurable[service].overview()
                : '';
        },

        setConfigOverview(service) {
            if (this.configurable[service].overviewFormat === undefined) {
                return;
            }

            this.configurable[service].overview(this.buildOverview(service));
        },

        buildOverview(service) {
            return this.configurable[service].overviewFormat.replace(
                /\{\$([a-zA-Z_]\w*)\}/g,
                function (match, name) {
                    return this.getFieldValue(this.configurable[service].fieldsetElement, name);
                }.bind(this)
            );
        },

        getFieldValue(fieldset, fieldName) {
            const field = fieldset.elems().find((element) => element.name.indexOf(fieldName) !== -1);
            if (field !== undefined) {
                switch (field.formElement) {
                    case 'select':
                        return field.getOption(field.value()).label;
                    case 'input':
                    default:
                        return field.value();
                }
            }
        },

        servicesChanged(values) {
            values = values || [];
            registry.async('index = polkurier_modal_manager')(function (manager) {
                values.forEach(function (service) {
                    if (this.previous && this.previous.indexOf(service) === -1
                        // && this.configurable[service].dirty() === false
                    ) {
                        this.configurable[service].dirty(true);
                        manager.configure(
                            this.configurable[service].fieldsetElement,
                            service,
                            this.setConfigOverview.bind(this)
                        );
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
                manager.configure(this.configurable[service].fieldsetElement, service, this.setConfigOverview.bind(this));
            }.bind(this));
        }
    });
});
