/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * A section of the shipment screen: a titled admin page section holding its parts, with an
 * optional action next to the title that opens the section's modal.
 *
 * A section also says what in it still needs attention: of the provider's "issues", which the
 * summary works out for the whole screen (see rules.js), those of what it holds ("cards"). What
 * would stop the shipment is only marked once placing it has been tried, and is then kept up to
 * date; softer warnings show as they come up.
 */
define([
    'ko',
    'uiCollection',
    'uiRegistry'
], function (ko, Collection, registry) {
    'use strict';

    return Collection.extend({
        defaults: {
            template: 'Polkurier_Shipping/shipment/workspace/section',
            label: '',
            actionLabel: '',
            action: '',
            additionalClasses: '',
            cards: [],
            // Where each card's problems are fixed, from the form's XML: a modal of the form, or
            // a part of the section that adds what's missing.
            fixModals: {},
            fixParts: {},
            provider: '${ $.ns }.${ $.ns }_data_source',
            formName: '${ $.ns }.${ $.ns }'
        },

        initialize: function () {
            this._super();
            this.errors = ko.observableArray([]);
            this.warnings = ko.observableArray([]);
            if (this.cards.length) {
                // At once: placing the shipment looks for the sections marked right after.
                this.source.on('issues', this.refreshIssues.bind(this), this.name);
                this.source.on('validated', this.refreshIssues.bind(this), this.name);
                this.refreshIssues();
            }

            return this;
        },

        /**
         * Takes the section's listeners off the form's data as it goes.
         */
        destroy: function () {
            this.source.off(this.name);
            this._super();
        },

        refreshIssues: function () {
            const cards = this.cards,
                issues = (this.source.get('issues') || []).filter(function (issue) {
                    return cards.indexOf(issue.card) >= 0;
                });

            this.errors(this.source.get('validated') ? issues.filter(function (issue) {
                return issue.blocking;
            }) : []);
            this.warnings(issues.filter(function (issue) {
                return !issue.blocking;
            }));
        },

        runAction: function () {
            registry.get(this.action, function (modal) {
                modal.openModal();
            });
        },

        /**
         * Where the problem is fixed: what the issue says, or else where its card's problems are.
         *
         * @param {Object} issue
         * @returns {{part: String}|{modal: String}|null}
         */
        fixTarget: function (issue) {
            const fix = issue.fix;

            if (fix === false) {
                return null;
            }
            if (fix && fix.modal) {
                return {modal: fix.modal};
            }
            if (fix && (fix.part || fix.parcel !== undefined)) {
                return {part: fix.part || this.fixParts[issue.card]};
            }
            if (this.fixParts[issue.card]) {
                return {part: this.fixParts[issue.card]};
            }

            return this.fixModals[issue.card] ? {modal: this.fixModals[issue.card]} : null;
        },

        /**
         * @param {Object} issue
         * @returns {Boolean} whether the problem is fixed somewhere else than the section itself
         */
        canFix: function (issue) {
            const target = this.fixTarget(issue);

            return Boolean(target && (target.part || target.modal));
        },

        /**
         * Takes the merchant to where the problem is fixed: a modal of the form, or a part of the
         * section, which is handed the issue to open what fixes it.
         *
         * @param {Object} issue
         */
        fix: function (issue) {
            const target = this.fixTarget(issue);

            if (target && target.modal) {
                registry.get(this.formName + '.' + target.modal, function (modal) {
                    modal.openModal();
                });
            } else if (target && target.part) {
                registry.get(this.name + '.' + target.part, function (part) {
                    part.fix(issue);
                });
            }
        }
    });
});
