<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Troubleshooting

[User guide](README.md) › Troubleshooting

## Getting more information

Turn on **Log failed requests** on the [configuration
page](configuration.md#connecting-your-account), repeat what failed, and look at
`var/log/polkurier.log`. For more detail, select the kinds of requests under **Log all traffic
of**. It's safe to send the log to Polkurier support: your API token, PINs and confirmation codes
are masked.

## Connection

**"Test connection" fails.** Check the customer ID and token for typos and spaces, and that
**Sandbox** matches the kind of account the credentials belong to. Sandbox and production accounts
don't accept each other's credentials. The credentials are set at the default scope only, for the
whole installation.

**Everything worked, then it stopped.** The API token may have been regenerated in the Polkurier
panel. Enter the new one.

## Checkout

**No Polkurier methods at checkout.** Check, for the website in question:

- **Enabled** is Yes on the configuration page;
- at least one carrier in the carriers list is **Enabled** (the list shows only the carriers your
  account offers and you selected under **Available carriers**);
- the cart reaches one of the carrier's price tiers;
- **Ship to Applicable Countries** doesn't exclude the customer's country, and the carrier
  delivers there (carriers for Poland only aren't offered abroad).

**A carrier is missing.** It may not be on your account any more, or not selected under
**Available carriers** (either way it isn't in the carriers list), or the cart doesn't reach any of
its price tiers. Click **Synchronize with Polkurier**
if Polkurier added it recently. A carrier that delivers only to lockers or pickup points is also
left out while the pickup point map can't be used, since customers would have no way to choose a
point; it comes back by itself within a couple of minutes of the map working again. The module's
log says when this happens.

**The map doesn't open.** If your server sends its own Content Security Policy headers (outside
Magento), allow Polkurier's map domain; Magento's own policy already allows it. Also check the
browser console for scripts blocked by an ad blocker.

**Customers can't continue without a point, but the map shows none.** The map may be limited too
much: check **Show only points that** under **Pickup point map**.

**Cash on delivery is missing at checkout.** The chosen carrier or point can't collect cash. That's
intended; the customer can pick another carrier or point.

## Shipping

**No "Create Shipping Label" checkbox on the New Shipment page.** Magento only shows it for
carriers that create labels. Check that the order was placed with a Polkurier method, and that
**Shipping labels on Magento's shipment page** is on for the order's website.

**"Shipping labels can't be created. Verify that the store information and settings are
complete".** This comes from Magento, not Polkurier. Fill in the store name and phone number, the
shipping origin, and the first and last name of your admin user (see
[Before your first label](shipping-an-order.md#before-your-first-label)).

**Polkurier refuses the shipment.** The message says why. Common reasons:

- a missing or invalid phone number or postcode in the shipping address;
- a parcel too heavy or too big for the carrier, or a parcel type it doesn't take;
- no pickup point for a carrier that delivers only to points;
- no collection date, or a date or time window the courier doesn't come on;
- cash on delivery without a bank account, or with a carrier that doesn't offer it.

**The shipment was created but has no label.** Polkurier hadn't issued the label yet when the
shipment was saved. Open the shipment from the **Polkurier order** column on the order's Shipments
tab, or from **Polkurier > Sales > Shipments**, and use **Print label**; or click **Create Shipping
Label** on the Magento shipment later; that fetches the label of the existing Polkurier order and
doesn't place a new one.

**"An earlier Polkurier order for this order got no answer and may exist".** A previous attempt is
still unclear. Wait for the automatic check, or click **Reconcile** on that shipment. See
[When the result is unclear](shipping-an-order.md#when-the-result-is-unclear).

**The street was split wrongly.** Correct the street, house number and flat number on the shipment
screen before placing the shipment. On Magento's shipment page the address is used as the module
splits it; use the shipment screen for unusual addresses.

## Scheduled jobs

**Statuses, synced data or unclear shipments don't update on their own.** All three rely on
Magento's cron. Check that `bin/magento cron:run` is scheduled on the server, and that the jobs
are switched on under **Synchronization** on the configuration page.

## Addresses, bank accounts and templates

**An entry shows "Error".** Polkurier refused the change. The reason is in the **Sync error**
column; fix the entry and save it again.

**A bank account stays "Awaiting confirmation".** Open it, enter the security PIN or request and
enter a confirmation code, and save.

**A change made in the Polkurier panel doesn't show up.** It comes with the next hourly sync. Click
**Synchronize with Polkurier** to get it now.

Next: [Known limitations](known-limitations.md)
