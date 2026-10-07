<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ All Rights Reserved
-->
# Synchronization

[Developer guide](README.md) › Synchronization

Addresses, bank accounts and pack templates exist both locally and on the Polkurier account, and
the merchant can edit them on either side.

## Pieces

| Class | Role |
|---|---|
| `Model\Sync\SyncEngine` | brings both sides level for one kind of entity |
| `Model\Sync\AdapterInterface` | what the engine needs to know about one kind: fetch, map, save and delete locally |
| `Model\Sync\PushableAdapterInterface` | for kinds the engine sends by itself (addresses, pack templates): push, delete remotely |
| `Model\<Entity>\<Entity>SyncAdapter` | the adapters for addresses, bank accounts and pack templates |
| `Enum\SyncEntityEnum` | the kinds of entries synchronized: `Address`, `BankAccount`, `PackTemplate`, each with its admin label |
| `Model\Sync\Synchronization` | runs every adapter, or those of the kinds asked for |
| `Model\<Entity>\<Entity>Handler` | save and delete from the admin screens, sending the entry straight away |

`Model\Sync\Synchronization` takes the three adapters in its constructor, one per kind; each says
which kind it handles through `getEntityType()`.

## One entry from the admin

Saving or deleting an entry in the admin sends that entry alone, through
`Synchronization::push()`: the engine runs as for a full sync, with the remote list read for
conflicts and natural keys, but only for that entry. Other changes, on either side, wait for the
hourly run or the *Synchronize with Polkurier* button. It returns the run's counts
(`Model\Sync\SyncResult`), not a line of text: the admin action adds it to its one message, and a
mass delete adds up those of all its rows.

## One at a time

`Model\Sync\SyncLock` lets one writer at a time change the synchronized entries: the hourly cron
and saving or deleting in the admin, which synchronizes straight away. Without it, both could send
the same new entry, or one could save its older copy of a row over the other's change. The lock
(`polkurier_sync`, Magento's lock manager) is taken with `run()` and is re-entrant within a
request, since an admin save holds it while it synchronizes. A request that can't get it within 20
seconds fails with a message to try again.

## How the engine decides

For each entry, the engine compares both sides with how the last sync left them. It keeps a hash of
the remote entry (`remote_hash`) and the time of the last sync (`synced_at`) in the local row.

The local side counts as changed when the row is `pending` (saved in the admin), `error` (a change
that didn't get through) or `unconfirmed`, or when `updated_at` is later than `synced_at`. The state
matters because the times are to the second: a row edited in the same second as the last sync
would otherwise look unchanged.

- Only the local side changed: push.
- Only the remote side changed: pull.
- Both changed: the later change wins. Local rows have `updated_at`; Polkurier doesn't report
  when its entries changed, so a remote change is dated to the sync that first sees it.
- Deleted locally: rows are soft-deleted first (`deleted_at`), so the delete can be pushed, then
  removed.
- Deleted remotely: the local row is removed, unless it changed locally since the last sync, in
  which case it's pushed again.
- New on either side: created on the other. Before an unlinked local entry is sent, it's matched
  against the remote entries nothing is linked to by its natural key (`getNaturalKey()`: the name
  of an address or pack template, the number of a bank account). Exactly one match links them, so
  an entry sent before whose link failed to be saved in Magento isn't sent twice. When saving the
  link fails, the remote id is logged.

A failed push leaves the row in the error state with the reason in `sync_error`; the next run tries
again. An entry that fails for any reason doesn't stop the others, a kind that can't be synchronized
doesn't stop the other kinds (its failure is logged and listed after their summaries, and it's an
error, `Exception\SynchronizationFailedException`, only when every kind asked for failed; the cron
task doesn't log that one again, since each kind's failure already is). `SyncEngine::run()` returns a `SyncResult` counting
what was created, pulled, pushed, deleted, left unchanged, left unconfirmed and what failed.

## When it runs

- Saving or deleting in the admin pushes that entry straight away (`Model\<Entity>\<Entity>Handler`).
- `Cron\SynchronizeTask` runs the full sync hourly, and `Cron\RefreshCarriersTask` refreshes the
  carrier list daily (`Model\CarrierConfig\CarrierConfigSynchronizer`), when `sync/enabled` is on and the
  credentials are set. The list read is cached for a day (`CarrierCatalog::CACHE_LIFETIME`).
- The *Synchronize with Polkurier* button on each settings screen runs it for that kind of entry,
  once the credentials are set.
- `Model\Sync\Synchronization` runs it from code, for all kinds or some:

  ```php
  $this->synchronization->synchronize([SyncEntityEnum::Address, SyncEntityEnum::PackTemplate]);
  ```

## Bank accounts

Polkurier accepts a bank account change only with the account holder's confirmation: the security
PIN, if the account locks bank account changes, or otherwise a two-factor code, one per save. The
sync can't supply either, so:

- the bank account adapter isn't a `PushableAdapterInterface`, so the engine leaves local changes and
  deletions in `STATE_UNCONFIRMED` instead of pushing them;
- `Model\BankAccount\BankAccountHandler` sends them from the form, through
  `Model\BankAccount\Publisher`, with the confirmation the merchant entered
  (`Model\BankAccount\Confirmation`), then records the result with `SyncEngine::pushed()`;
- `Publisher::isPinLocked()` tells the form which kind of confirmation to ask for, and
  `Publisher::requestCode()` sends the code.

The form asks for the confirmation as it's saved: its data provider
(`view/adminhtml/web/js/form/confirmed-provider.js`, set up in the form's XML) has the
code sent (`polkurier/bankaccount/sendCode`) and asks for it in a modal, or asks for the PIN where
bank accounts are locked, then submits the form with it; cancelled, nothing is posted. Deleting asks
for the PIN the same way (`form/bank-account-delete.js`). The payout form works alike
(`polkurier/payout/sendCode`), with the PIN optional beside the code.

The PIN, two-factor token and code are never stored, logged, kept in the form's persisted data
(`Controller\Adminhtml\AbstractSave::getTransientFields()`) or put into events.

Pulling bank accounts works as for the others.

Next: [Cash on delivery payouts](cash-on-delivery.md)
