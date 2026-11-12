/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * The data of a form Polkurier saves only once confirmed (a bank account, a payout). Saving first
 * asks for the confirmation in a modal: either the confirmation code, which Polkurier is asked to
 * send right then, or the security PIN, where the account locks the form's entries with one. The
 * form is saved once it's given; if the modal is cancelled, nothing is saved.
 */
define([
    'jquery',
    'mage/translate',
    'Magento_Ui/js/form/provider',
    'Magento_Ui/js/modal/alert',
    'Polkurier_Shipping/js/request',
    'Magento_Ui/js/modal/modal'
], function ($, $t, Provider, alert, request) {
    'use strict';

    return Provider.extend({
        defaults: {
            confirmation: {
                // Paths below "data": the URL that sends the code, and the flag that asks for the PIN
                // instead.
                codeUrl: 'urls.send_code',
                pinLocked: '',
                // Paths below "data" that receive what's typed in and the code's token.
                codeField: 'two_factor_code',
                tokenField: 'two_factor_token',
                pinField: '',
                // Also asks for the PIN beside the code, when the entry may be locked with one too.
                optionalPin: false,
                optionalPinNote: '',
                title: ''
            }
        },

        /**
         * @param {Object} [options]
         * @returns {Object}
         */
        save: function (options) {
            const settings = this.confirmation;

            if (settings.pinLocked && this.get('data.' + settings.pinLocked)) {
                this.ask(options, {pin: true});
            } else {
                this.sendCode(options, null);
            }

            return this;
        },

        /**
         * Has Polkurier send the code, then asks for it. When the code is sent again, the open modal
         * is reused.
         *
         * @param {Object} [options]
         * @param {jQuery|null} dialog
         */
        sendCode: function (options, dialog) {
            const body = $('body');

            body.trigger('processStart');
            request().post(this.get('data.' + this.confirmation.codeUrl), {}, {
                done: function (response) {
                    if (response.error) {
                        if (dialog) {
                            dialog.find('.polkurier-confirm__sent')
                                .text(response.message)
                                .addClass('message-error error');
                        } else {
                            alert({
                                title: $t('The confirmation code wasn\'t sent'),
                                content: $('<div/>').text(response.message).html()
                            });
                        }

                        return;
                    }
                    this.set('data.' + this.confirmation.tokenField, response.token);
                    if (dialog) {
                        dialog.find('.polkurier-confirm__sent')
                            .text(response.message)
                            .removeClass('message-error error');
                        dialog.find('[name=code]').val('').trigger('focus');
                    } else {
                        this.ask(options, {code: true, sent: response.message});
                    }
                }.bind(this),
                fail: function () {
                    alert({
                        title: $t('The confirmation code wasn\'t sent'),
                        content: $t('Polkurier could not be asked.')
                    });
                },
                always: function () {
                    body.trigger('processStop');
                }
            });
        },

        /**
         * Opens the modal asking for the code or the PIN; once confirmed, the form is saved with it.
         * The page has one such modal, filled in each time.
         *
         * @param {Object} [options]
         * @param {{code: Boolean, pin: Boolean, sent: String}} what
         */
        ask: function (options, what) {
            const settings = this.confirmation,
                dialog = this.getDialog(),
                field = function (name, label, type, note) {
                    const id = 'polkurier-confirm-' + name,
                        row = $('<div class="admin__field"/>')
                            .append($('<label class="admin__field-label"/>')
                                .attr('for', id)
                                .append($('<span/>').text(label)))
                            .append($('<div class="admin__field-control"/>').append(
                                $('<input class="admin__control-text"/>').attr({
                                    id: id,
                                    name: name,
                                    type: type,
                                    autocomplete: 'one-time-code'
                                })
                            ));

                    if (note) {
                        row.find('.admin__field-control').append($('<div class="admin__field-note"/>').text(note));
                    }

                    return row;
                };

            this.pending = {options: options, what: what};
            dialog.empty();
            if (what.code) {
                dialog.append($('<p class="polkurier-confirm__sent"/>').text(what.sent || ''));
                dialog.append(field('code', $t('Confirmation code'), 'text'));
                dialog.append($('<p/>').append(
                    $('<button type="button" class="action-link"/>')
                        .text($t('Send a new code'))
                        .on('click', this.sendCode.bind(this, options, dialog))
                ));
                if (settings.optionalPin && settings.pinField) {
                    dialog.append(field('pin', $t('Security PIN'), 'password', settings.optionalPinNote));
                }
            } else {
                dialog.append($('<p/>').text($t('Your Polkurier account locks this with a security PIN.')));
                dialog.append(field('pin', $t('Security PIN'), 'password'));
            }
            dialog.append($('<div class="message message-error error polkurier-confirm__error" role="alert"/>').hide());
            dialog.modal('openModal');
        },

        /**
         * @returns {jQuery} the page's confirmation modal
         */
        getDialog: function () {
            if (this.confirmDialog) {
                return this.confirmDialog;
            }
            const dialog = $('<div class="polkurier-confirm"/>').appendTo('body'),
                confirm = this.confirm.bind(this);

            dialog.on('keydown', 'input', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    confirm();
                }
            });
            dialog.modal({
                type: 'popup',
                title: this.confirmation.title || $t('Confirm with Polkurier'),
                modalClass: 'polkurier-confirm-modal',
                buttons: [
                    {text: $t('Cancel'), class: 'action-secondary', click: function () {
                        this.closeModal();
                    }},
                    {text: $t('Confirm and save'), class: 'action-primary', click: confirm}
                ],
                isOpened: function () {
                    dialog.find('input').first().trigger('focus');
                }
            });
            this.confirmDialog = dialog;

            return dialog;
        },

        /**
         * Takes the confirmation modal down with the form: it was added to the page's body.
         */
        destroy: function () {
            if (this.confirmDialog) {
                this.confirmDialog.modal('destroy').remove();
                this.confirmDialog = null;
            }
            this._super();
        },

        /**
         * Saves the form with what the modal was given, once it has what it asks for.
         */
        confirm: function () {
            const settings = this.confirmation,
                dialog = this.getDialog(),
                what = this.pending ? this.pending.what : {},
                error = dialog.find('.polkurier-confirm__error'),
                code = String(dialog.find('[name=code]').val() || '').trim(),
                pin = String(dialog.find('[name=pin]').val() || '').trim();

            if (what.code && code === '') {
                error.text($t('Enter the code.')).show();

                return;
            }
            if (what.pin && pin === '') {
                error.text($t('Enter the security PIN.')).show();

                return;
            }
            if (what.code) {
                this.set('data.' + settings.codeField, code);
            }
            if (settings.pinField) {
                this.set('data.' + settings.pinField, pin);
            }
            dialog.modal('closeModal');
            Provider.prototype.save.call(this, this.pending ? this.pending.options : undefined);
        }
    });
});
