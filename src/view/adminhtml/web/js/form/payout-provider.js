/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * The payout form's data: requested once confirmed with the code Polkurier sends then, and the
 * security PIN where the money box settings are locked with one (./confirmed-provider).
 */
define(['mage/translate', './confirmed-provider'], function ($t, ConfirmedProvider) {
    'use strict';

    return ConfirmedProvider.extend({
        defaults: {
            confirmation: {
                pinField: 'security_pin',
                optionalPin: true,
                optionalPinNote: $t('Only if the money box settings are locked with a PIN.'),
                title: $t('Confirm the payout')
            }
        }
    });
});
