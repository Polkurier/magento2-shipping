define([
    'Magento_Ui/js/form/form',
    'ko',
    'uiRegistry'
], function (Form, ko, registry) {
    'use strict';

    return Form.extend({
        packTemplates: {},
        carrierCode: null,

        initialize: function () {
            this._super();

            let data = registry.get(this.provider).data;
            this.carrierCode = ko.observable(data.carrier_code),
            this.packTemplates = data.pack_templates;

            return this;
        },
    });
});
