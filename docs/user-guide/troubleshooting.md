<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Troubleshooting

[User guide](README.md) › Troubleshooting

## Getting more information

When the message on screen isn't enough, use the module's log:

1. On the [configuration page](configuration.md#logging), check that **Log failed requests** is
   on. It is by default.
2. For more detail, select the kinds of requests under **Log all traffic of**.
3. Repeat what failed.
4. Get this month's [error log](../developer-guide/logging.md) and send it to Polkurier support.

The log is safe to send: your API token, PINs and confirmation codes are always hidden. So is
personal data, unless you turned on **Log personal data**.

## Connection

**"Test connection" fails.** Check the customer ID and token for typos and extra spaces. Check that
**Sandbox** matches the account: a sandbox account's credentials don't work with Sandbox off, and
a live account's don't work with it on. The credentials are set in the **Default Config** scope.

**Everything worked, then it stopped.** The API token may have been changed in the Polkurier
panel. Enter the new one.

## Checkout

**No Polkurier methods at checkout.** Check, for the website in question, that:

- **Enabled** is Yes on the configuration page;
- at least one carrier is **Enabled** under **Polkurier > Settings > Carriers**;
- the cart reaches one of the carrier's price tiers;
- **Ship to Applicable Countries** includes the customer's country, and the carrier delivers there
  (carriers for Poland only aren't offered abroad).

**A carrier is missing.** First look for it under **Polkurier > Settings > Carriers**.

- **It isn't in the list.** It may be new on your account: click **Synchronize with Polkurier**.
  Or it isn't selected under **Available carriers** on the configuration page. Or your account no
  longer offers it.
- **It's in the list.** The cart may not reach any of its price tiers. Or the carrier delivers only
  to lockers or pickup points and the map can't be used right now. It comes back by itself within a
  couple of minutes once the map works again.

**The map doesn't open.** Try a browser without an ad blocker; some block the map. If it still
doesn't open, check that the server doesn't
[block Polkurier's map](../developer-guide/configuration.md#api-and-map-hosts).

**Customers can't continue without a point, but the map shows none.** The map may be limited too
much. Check **Show only points that** under **Pickup point map**.

**Cash on delivery is missing at checkout.** The chosen carrier or point can't collect cash. The
customer can pick another carrier or point.

## Shipping

**No "Create Shipping Label" checkbox on the New Shipment page.** Magento shows it only for
carriers that create labels. Check that the order was placed with a Polkurier method.

**"Shipping labels can't be created. Verify that the store information and settings are
complete".** This message comes from Magento, not Polkurier. Fill in the store name and phone
number, the shipping origin, and the first and last name of your admin user (see
[Before your first label](shipping-an-order.md#before-your-first-label)).

**Polkurier refuses the shipment.** The message says why. Common reasons:

- a missing or invalid phone number or postcode in the shipping address;
- a parcel too heavy or too big for the carrier, or a parcel type it doesn't take;
- no pickup point for a carrier that delivers only to points;
- no collection date, or a date or time window the courier doesn't come on;
- cash on delivery without a bank account, or with a carrier that doesn't offer it.

**The shipment was created but has no label.** Polkurier hadn't issued the label yet when the
shipment was saved. Open the shipment and click **Print label**. You'll find it under **Polkurier >
Sales > Shipments**, or through the **Polkurier order** column on the order's Shipments tab. You can
also click **Create Shipping Label** on the Magento shipment later. That fetches the label of the
existing Polkurier shipment; it doesn't place a new one.

**"An earlier Polkurier order for this order got no answer and may exist".** An earlier attempt is
still unclear. Wait for the automatic check, or click **Reconcile** on that shipment. See
[When the result is unclear](shipping-an-order.md#when-the-result-is-unclear).

**The street was split wrongly.** On the shipment screen, correct the street, house number and flat
number before placing the shipment. Magento's shipment page uses the address as the module splits
it, so use the shipment screen for unusual addresses.

## Scheduled jobs

**Statuses, synced data or unclear shipments don't update on their own.** All three rely on
Magento's scheduled jobs (cron). Check that the jobs are switched on under **Synchronization** on
the configuration page, and that [cron runs on the server](../developer-guide/installation.md#cron).

**Statuses still wait for the schedule, although the webhook is set up.** Check that:

- the URL in the Polkurier panel is the one shown under **Status webhook secret**;
- the URL starts with https and the shop is reachable from the internet;
- the secret pasted is the one the panel shows for that webhook. A wrong secret is refused.

## Addresses, bank accounts and templates

**An entry shows "Error".** Polkurier refused the change. The reason is in the **Sync error**
column. Fix the entry and save it again.

**A bank account stays "Awaiting confirmation".** Open it and save it. In the window that opens,
enter the confirmation code Polkurier sends you, or the security PIN.

**A change made in the Polkurier panel doesn't show up.** Addresses, bank accounts and pack
templates come in with the next hourly sync, carriers with the next daily one. Click **Synchronize
with Polkurier** to get it now.

Next: [Known limitations](known-limitations.md)
