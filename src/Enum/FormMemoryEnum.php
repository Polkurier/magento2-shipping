<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum;

/**
 * Where an admin form's data waits, in the session, when saving it failed, for the form to show
 * again: the save action keeps it under the key, and the form's data provider takes it back.
 */
enum FormMemoryEnum: string
{
    case Address = 'polkurier_address';

    case BankAccount = 'polkurier_bank_account';

    case PackTemplate = 'polkurier_pack_template';

    case Carrier = 'polkurier_carrier';

    case Payout = 'polkurier_payout';

    /**
     * The shipment screen: a draft Polkurier refused (RejectedDraftMemory).
     */
    case Shipment = 'polkurier_shipment_form';
}
