/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * What a field shown only for some choices of the form adds to its element: it's visible while
 * each field named in shownWhen has the value given, e.g. {payout_type: 'ACCOUNT'}. Hidden, it
 * isn't validated.
 */
define(['underscore'], function (_) {
    'use strict';

    return {
        defaults: {
            shownWhen: {}
        },

        initialize: function () {
            this._super();
            _.keys(this.shownWhen).forEach(function (field) {
                this.source.on('data.' + field, this.followChoices.bind(this));
            }, this);
            this.followChoices();

            return this;
        },

        followChoices: function () {
            this.visible(_.every(this.shownWhen, function (value, field) {
                return String(this.source.get('data.' + field)) === String(value);
            }, this));
        }
    };
});
