/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The carrier select. Publishes what the chosen carrier supports as data.features, which the rest
 * of the form imports to show, hide and narrow its fields.
 */
define(['Magento_Ui/js/form/element/select'], function (Select) {
    'use strict';

    return Select.extend({
        defaults: {
            listens: {
                value: 'publishFeatures'
            }
        },

        initialize: function () {
            this._super();
            this.publishFeatures(this.value());

            return this;
        },

        /**
         * @param {String} code
         */
        publishFeatures: function (code) {
            const carriers = this.source.get('data.carriers') || {},
                carrier = carriers[code] || null,
                fields = carrier ? carrier.fields || [] : [];

            this.source.set('data.features', {
                known: !!carrier,
                point_allowed: carrier ? carrier.point !== 'NONE' : true,
                point_required: carrier ? carrier.point === 'REQUIRED' : false,
                providers: carrier ? carrier.providers : [],
                cod: carrier ? carrier.cod : true,
                insurance: carrier ? carrier.insurance : true,
                insurance_with_cod: carrier ? carrier.insurance_with_cod : false,
                services: carrier ? carrier.services : null,
                drop_off: carrier ? carrier.drop_off : true,
                pickup_date: carrier ? carrier.pickup_date : true,
                pickup_date_required: carrier ? carrier.pickup_date_required : false,
                pickup_time_required: carrier ? carrier.pickup_time_required : false,
                fields: fields,
                has_fields: fields.length > 0
            });
            // Parcels can't be dropped off with a carrier that only collects them.
            if (carrier && !carrier.drop_off && this.source.get('data.pickup_mode') === 'drop_off') {
                this.source.set('data.pickup_mode', 'courier');
            }
        }
    });
});
