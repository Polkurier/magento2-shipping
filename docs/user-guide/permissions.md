<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Permissions

[User guide](README.md) › Permissions

The module adds its own permissions to the role editor in **System > Permissions > User Roles**,
under **Role Resources**. They let you, for example, have someone print labels without being able
to place or cancel shipments, or keep payouts to a few people.

| Permission | Allows |
|---|---|
| **Polkurier** | the Polkurier menu (needed for everything below) |
| **Orders**, **Store shipments** | the lists of Magento's orders and shipments shipped with Polkurier; their actions also need Magento's own permissions |
| **Shipments** | see shipments, print labels and protocols, update statuses |
| &nbsp;&nbsp;**Create shipments** | place shipments (on either page), reconcile unclear ones, create missing Magento shipments, book collective pickups |
| &nbsp;&nbsp;**Cancel shipments** | cancel shipments in Polkurier |
| **Cash on delivery** | see payouts |
| &nbsp;&nbsp;**Request payouts** | request and cancel payouts |
| **Settings** | the Settings menu |
| &nbsp;&nbsp;**Addresses**, **Bank accounts**, **Pack templates**, **Carriers** | manage each of them |

The configuration page is controlled by **Stores > Settings > Configuration > Polkurier**.

Shipping on Magento's own shipment page needs Magento's usual permission to create shipments
(**Sales > Operations > Orders > Actions > Ship**) as well as **Create shipments** above. Without
the latter, Magento can still create plain shipments, but not Polkurier labels.

The REST API uses the same permissions for integrations; see the
[developer guide](../developer-guide/rest-api.md).

Next: [Troubleshooting](troubleshooting.md)
