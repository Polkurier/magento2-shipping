/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * Makes a form element shown only for some choices of the form: it's visible while each field
 * named in shownWhen has the given value, e.g. {payout_type: 'ACCOUNT'}. While hidden, it isn't
 * validated.
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
