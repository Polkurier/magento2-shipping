<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Events

[Developer guide](README.md) › Events

Whenever the module changes something on the Polkurier account, it dispatches an event once
Polkurier has confirmed the change and Magento has recorded it. It happens the same way whether the
change came from the admin, Magento's label flow, the REST API or a cron job. The events are the
cases of the backed enum `Api\ApiEventEnum`, whose values are the event names and whose cases
document each event's data. `Model\ApiEventDispatcher` dispatches them, and accepts only those
cases, so the module can't dispatch a name that isn't on the list.

Observe them in `events.xml` by name, as in the table below. In PHP, refer to a name through the
enum, for example `ApiEventEnum::OrderCreateAfter->value`; `ApiEventEnum::cases()` lists them
all.

## Reference

Every event carries `store_id`, the store whose account was changed. The other keys:

| Event | When | Data |
|---|---|---|
| `polkurier_api_order_create_after` | a Polkurier order was placed for a Magento order | `shipment`, `order`, `source`, `request`, `response` |
| `polkurier_api_order_cancel_after` | a Polkurier order was cancelled | `shipment`, `order`, `request`, `response` |
| `polkurier_api_pickup_create_after` | a collective pickup was booked | `shipments` (those booked), `failed` (reasons by order number), `request`, `response` |
| `polkurier_api_address_save_after` | a sender address was created or updated on the account | `address`, `is_new`, `request`, `response` |
| `polkurier_api_address_delete_after` | a sender address was deleted from the account | `address`, `request` |
| `polkurier_api_pack_template_save_after` | a pack template was created or updated | `pack_template`, `is_new`, `request`, `response` |
| `polkurier_api_pack_template_delete_after` | a pack template was deleted | `pack_template`, `request` |
| `polkurier_api_bank_account_save_after` | a bank account was created or updated, with confirmation | `bank_account`, `is_new`, `response` |
| `polkurier_api_bank_account_delete_after` | a bank account was deleted | `bank_account` |
| `polkurier_api_two_factor_code_request_after` | a two-factor code was sent to the account holder | `namespace` (what the code is for) |
| `polkurier_api_payout_create_after` | a cash on delivery payout was requested | `order_numbers` (empty for everything available), `payout_type`, `fee_payment_type`, `response` |
| `polkurier_api_payout_cancel_after` | a payout was cancelled | `request`, `response` |

`shipment`, `address`, `pack_template` and `bank_account` are the module's data interfaces;
`order` is Magento's `OrderInterface`; `request` and `response` are the SDK messages that were sent
and received.

## Things worth knowing

- `polkurier_api_order_create_after` fires once for every placed order, including one whose
  placing couldn't be confirmed at first. `source` tells you which case it is:

  | `source` | |
  |---|---|
  | `create` | placed from the shipment screen, the REST API or your own code |
  | `label` | placed for a label on Magento's shipment page; the Magento shipment is saved right after the event, so `shipment_id` isn't set yet |
  | `reconcile` | an unconfirmed attempt, found on the account later |
  | `adopt` | an unconfirmed attempt, linked by hand to a given order number |

  For `reconcile` and `adopt` there's no `request` or `response`, since the answer never arrived.
  Nothing is dispatched while the outcome is still unknown, or for a refused order.
- Address and pack template events fire for changes pushed by the sync as well as for saves in the
  admin. `is_new` says whether the entry was created on the account.
- The bank account and payout request events don't include the request, because it carries the
  confirmation code or security PIN. No event ever carries the API token, a PIN, a two-factor token
  or a code.
- The change has already happened on Polkurier's side when your observer runs. An exception from
  an observer is written to `var/log/polkurier.log` and not passed on. Otherwise a failing
  observer could make a placed order or a requested payout look failed, and someone might repeat
  it. If your reaction must not be lost, record it somewhere durable (a message queue, say) rather
  than doing slow work inline.

## Example

```xml
<!-- etc/events.xml -->
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:noNamespaceSchemaLocation="urn:magento:framework:Event/etc/events.xsd">
    <event name="polkurier_api_order_create_after">
        <observer name="vendor_module_notify_warehouse" instance="Vendor\Module\Observer\NotifyWarehouse" />
    </event>
</config>
```

```php
namespace Vendor\Module\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;

class NotifyWarehouse implements ObserverInterface
{
    public function __construct(private readonly \Vendor\Module\Model\Warehouse $warehouse) {}

    public function execute(Observer $observer): void
    {
        /** @var ShipmentInterface $shipment */
        $shipment = $observer->getEvent()->getData('shipment');
        $this->warehouse->expect($shipment->getPolkurierNumber(), $shipment->getWaybills());
    }
}
```

## Magento's own model events

These events are about the Polkurier account. Changes to the module's own tables still dispatch
Magento's usual `<prefix>_save_after` and `<prefix>_delete_after` events. The prefixes are
`polkurier_shipment`, `polkurier_carrier_config`, `polkurier_address`, `polkurier_bank_account`,
`polkurier_pack_template` and `polkurier_order_point`. A saved shipment isn't necessarily a placed
one, so check `getState()`.

Next: [Extending the module](extending.md)
