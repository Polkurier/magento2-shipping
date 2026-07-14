define([
    'Magento_Ui/js/form/element/select',
    'uiRegistry'
], function (
    Select,
    registry
) {
    'use strict';

    return Select.extend({
        defaults: {
            listens: {
                value: 'templateChanged'
            },
            imports: {
                packageTemplates: '${ $.provider }:data.pack_templates'
            }
        },
        initialize() {
            this._super();

            return this;
        },
        templateChanged: function (templateId) {
            if (!templateId) {
                return;
            }
            var template = this.packageTemplates[templateId];
            if (!template) {
                return;
            }

            this.setValue('type', template.type);
            this.setValue('length', template.length);
            this.setValue('width', template.width);
            this.setValue('height', template.height);
            this.setValue('weight', template.weight);
        },

        setValue(field, value) {
            registry.get(this.parentName + '.pack_' + field).value(value);
        }
    });

});
