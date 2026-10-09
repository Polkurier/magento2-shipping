<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Permissions

[User guide](README.md) › Permissions

Permissions are set in **System > Permissions > User Roles**. Open a role and go to **Role
Resources**. The module's permissions are under **Polkurier**:

- **Polkurier**: the Polkurier menu. Needed for everything below.
  - **Sales**: the Sales menu. Its **Orders** item opens Magento's orders list, so it also needs
    Magento's own **Sales > Operations > Orders**.
    - **Shipments**: see shipments, print labels and protocols, update statuses.
      - **Create shipments**: place shipments (on either page), reconcile unclear ones, create
        missing Magento shipments and book collective pickups.
      - **Cancel shipments**: cancel shipments in Polkurier.
    - **Cash on delivery payouts**: see payouts.
      - **Request payouts**: request and cancel payouts.
  - **Settings**: the Settings menu.
    - **Carriers**: manage carriers.
    - **Addresses**: manage addresses.
    - **Bank accounts**: manage bank accounts.
    - **Pack templates**: manage pack templates.

**The configuration page** has no permission of its own. It's part of Magento's **Delivery
Methods** settings, so it needs **Stores > Settings > Configuration > Delivery Methods Section**.

**Shipping on Magento's own shipment page** needs two permissions: Magento's usual one for creating
shipments (**Sales > Operations > Orders > Actions > Ship**) and **Create shipments** above.
Without **Create shipments**, Magento can still create plain shipments, but not Polkurier labels.

Next: [Troubleshooting](troubleshooting.md)
