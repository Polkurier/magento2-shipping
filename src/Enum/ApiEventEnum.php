<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum;

/**
 * Events dispatched after the module changed something on the Polkurier account. The value is the
 * event name to observe in events.xml.
 *
 * Each is dispatched once the change is confirmed by Polkurier and recorded in Magento. Every event
 * carries "store_id", the order's store for an event about an order and null for one about the
 * account, plus the keys listed below.
 * "request" and "response" are the SDK messages that were sent and received.
 *
 * The change has already happened when a listener runs, so an exception thrown by a listener is
 * logged and does not reach the caller: a failing listener must not make a placed order or a
 * requested payout look failed, and so tempt anyone into repeating it.
 *
 * Secrets never travel in these events: no API token, security PIN, two-factor token or code.
 *
 * @api
 */
enum ApiEventEnum: string
{
    /**
     * A Polkurier order was placed for a Magento order.
     *
     * Keys: "shipment" (Data\ShipmentInterface), "order" (\Magento\Sales\Api\Data\OrderInterface),
     * "source" ("create" when the answer confirmed it, "label" when it was placed for a shipping
     * label on Magento's shipment page, whose Magento shipment is saved right after, "reconcile" or
     * "adopt" when an unconfirmed attempt was found to have been placed), "request"
     * (CreateOrderMessage, only for "create" and "label"), "response" (CreatedOrderMessage, only
     * for "create" and "label").
     */
    case OrderCreateAfter = 'polkurier_api_order_create_after';

    /**
     * A Polkurier order was cancelled.
     *
     * Keys: "shipment", "order", "request" (OrderIdMessage), "response" (CancellationMessage).
     */
    case OrderCancelAfter = 'polkurier_api_order_cancel_after';

    /**
     * A collective pickup was booked.
     *
     * Keys: "shipments" (Data\ShipmentInterface[], those booked), "failed" (array<string, string>,
     * reasons by Polkurier order number), "request" (MultiPickupRequestMessage),
     * "response" (MultiPickupResultMessage).
     */
    case PickupCreateAfter = 'polkurier_api_pickup_create_after';

    /**
     * A sender address was created or updated on the account.
     *
     * Keys: "address" (Data\AddressInterface), "is_new" (bool), "request" (Address\SaveMessage),
     * "response" (the SDK's Address).
     */
    case AddressSaveAfter = 'polkurier_api_address_save_after';

    /**
     * A sender address was deleted from the account.
     *
     * Keys: "address" (Data\AddressInterface), "request" (Address\DeleteMessage).
     */
    case AddressDeleteAfter = 'polkurier_api_address_delete_after';

    /**
     * A pack template was created or updated on the account.
     *
     * Keys: "pack_template" (Data\PackTemplateInterface), "is_new" (bool),
     * "request" (PackTemplate\SaveMessage), "response" (the SDK's PackTemplate).
     */
    case PackTemplateSaveAfter = 'polkurier_api_pack_template_save_after';

    /**
     * A pack template was deleted from the account.
     *
     * Keys: "pack_template" (Data\PackTemplateInterface), "request" (PackTemplate\DeleteMessage).
     */
    case PackTemplateDeleteAfter = 'polkurier_api_pack_template_delete_after';

    /**
     * A bank account was created or updated on the account, with the merchant's confirmation.
     *
     * Keys: "bank_account" (Data\BankAccountInterface), "is_new" (bool),
     * "response" (the SDK's BankAccount). The request is left out: it carries the code or PIN.
     */
    case BankAccountSaveAfter = 'polkurier_api_bank_account_save_after';

    /**
     * A bank account was deleted from the account.
     *
     * Keys: "bank_account" (Data\BankAccountInterface).
     */
    case BankAccountDeleteAfter = 'polkurier_api_bank_account_delete_after';

    /**
     * A two-factor code was sent to the account holder, e.g. for a payout.
     *
     * Keys: "namespace" (TwoFactorNamespaceEnum: MONEY_BOX_PAYOUT or CHANGE_BANK_ACCOUNT_NUMBER).
     */
    case TwoFactorCodeRequestAfter = 'polkurier_api_two_factor_code_request_after';

    /**
     * A cash on delivery payout was requested. Money is moving.
     *
     * Keys: "order_numbers" (string[], empty for everything available), "payout_type"
     * (MoneyBoxPayoutTypeEnum), "fee_payment_type" (MoneyBoxFeePaymentTypeEnum),
     * "response" (MoneyBoxPayoutMessage). The request is left out: it carries the code and PIN.
     */
    case PayoutCreateAfter = 'polkurier_api_payout_create_after';

    /**
     * A cash on delivery payout was cancelled.
     *
     * Keys: "request" (CancelMoneyBoxPayoutMessage), "response" (MoneyBoxPayoutMessage).
     */
    case PayoutCancelAfter = 'polkurier_api_payout_cancel_after';
}
