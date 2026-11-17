<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Permissions

[User guide](README.md) › Permissions

Permissions are set in **System > Permissions > User Roles**. Open a role and go to **Role
Resources**.

Polkurier shipments use Magento's own shipment permissions, so a role that handles shipments can
use Polkurier without any more setup:

- **Sales > Operations > Shipments**: see Polkurier shipments, print labels and protocols, update
  statuses.
  - **Cancel Polkurier orders**: cancel Polkurier orders. Cancelling may cost money, so it has a
    permission of its own.
- **Sales > Operations > Orders > Actions > Ship**: place Polkurier orders (on either page, and with
  Magento's **Create Shipping Label**), discard ones that weren't placed, link unclear ones to their
  Polkurier order, create missing Magento shipments and book collective pickups.

The **Polkurier** menu shows to anyone with Magento's **Sales** permission, with only the items the
role may open. The module's other permissions are under **Polkurier**:

- **Polkurier**
  - **Sales**
    - **Cash on delivery payouts**: see payouts.
      - **Request payouts**: request and cancel payouts.
  - **Settings**
    - **Addresses**: manage addresses.
    - **Bank accounts**: manage bank accounts.
    - **Pack templates**: manage pack templates.

**Orders** in the Polkurier menu opens Magento's orders list, so it needs Magento's own **Sales >
Operations > Orders**.

**Carriers** and **the configuration page** have no permission of their own. Both are part of
Magento's **Delivery Methods** settings, so they need **Stores > Settings > Configuration >
Delivery Methods Section**. That includes choosing the carriers' default drop-off points. The
**Settings** menu shows to anyone with that permission, with only the items the role may open, so
**Addresses**, **Bank accounts** and **Pack templates** need it too to be reached from the menu.

Next: [Troubleshooting](troubleshooting.md)
