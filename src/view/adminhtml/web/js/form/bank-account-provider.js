/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The bank account form's data: saved once confirmed with the code Polkurier sends then, or with
 * the security PIN where the account locks bank accounts with one (./confirmed-provider).
 */
define(['mage/translate', './confirmed-provider'], function ($t, ConfirmedProvider) {
    'use strict';

    return ConfirmedProvider.extend({
        defaults: {
            confirmation: {
                pinLocked: 'pin_locked',
                pinField: 'confirmation_pin',
                title: $t('Confirm the bank account')
            }
        }
    });
});
