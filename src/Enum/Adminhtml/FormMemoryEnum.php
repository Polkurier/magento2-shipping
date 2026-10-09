<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum\Adminhtml;

/**
 * Session keys for an admin form's data when saving it failed, so the form can show the data again.
 * The save action keeps the data under the key, and the form's data provider takes it back.
 */
enum FormMemoryEnum: string
{
    case Address = 'polkurier_address';

    case BankAccount = 'polkurier_bank_account';

    case PackTemplate = 'polkurier_pack_template';

    case Carrier = 'polkurier_carrier';

    case Payout = 'polkurier_payout';

    /**
     * The shipment screen: a draft Polkurier refused
     * ({@see \Polkurier\Shipping\Model\Shipment\Placement\RejectedDraftStorage}).
     */
    case Shipment = 'polkurier_shipment_form';
}
