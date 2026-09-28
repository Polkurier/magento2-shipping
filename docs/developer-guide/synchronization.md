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
| `Model\Sync\SyncEngine` | brings both sides level for one kind of entity and one website |
| `Model\Sync\AdapterInterface` | what the engine needs to know about one kind: fetch, map, push, delete |
| `Model\Sync\Adapter\*` | the adapters for addresses, bank accounts and pack templates |
| `Api\SyncEntityEnum` | the kinds of entries synchronized: `Address`, `BankAccount`, `PackTemplate`, each with its admin label |
| `Model\Sync\Synchronization` | runs every adapter for a website, or those of the kinds asked for; implements `Api\SynchronizationInterface` |
| `Model\Sync\RemoteClockInterface` | when a remote entry last changed |
| `Model\Admin\*Handler` | save and delete from the admin screens, pushing straight away |

The adapters are registered in `etc/di.xml` as the `adapters` argument of
`Model\Sync\Synchronization`, one per kind; each says which kind it handles through
`getEntityType()`.

## How the engine decides

For each entry, the engine compares both sides with how the last sync left them. It keeps a hash of
the remote entry (`remote_hash`) and the time of the last sync (`synced_at`) in the local row.

- Only the local side changed: push.
- Only the remote side changed: pull.
- Both changed: the later change wins. Local rows have `updated_at`; the remote side's time comes
  from `RemoteClockInterface`.
- Deleted locally: rows are soft-deleted first (`deleted_at`), so the delete can be pushed, then
  removed.
- Deleted remotely: the local row is removed, unless it changed locally since the last sync, in
  which case it's pushed again.
- New on either side: created on the other.

A failed push leaves the row in the error state with the reason in `sync_error`; the next run tries
again. `run()` returns a `SyncResult` counting what was created, pulled, pushed, deleted, left
unchanged, left unconfirmed and what failed.

## The remote clock

Polkurier doesn't report modification times for these entries yet. The current implementation,
`Model\Sync\DetectedChangeClock`, dates a remote change to the sync that first sees it. When real
modification times become available, only this preference needs to change.

## When it runs

- Saving or deleting in the admin pushes that entry straight away (`Model\Admin\*Handler`).
- `Cron\Synchronize` runs the full sync hourly, for websites with credentials, when
  `sync/enabled` is on.
- The *Synchronize with Polkurier* button on each settings screen runs it for that kind of entry,
  for every website with credentials.
- `Api\SynchronizationInterface` lets you run it from your own code, for all kinds or some:

  ```php
  $this->synchronization->synchronize($websiteId, [SyncEntityEnum::Address, SyncEntityEnum::PackTemplate]);
  ```

## Bank accounts

Polkurier accepts a bank account change only with the account holder's confirmation: the security
PIN, if the account locks bank account changes, or otherwise a two-factor code, one per save. The
sync can't supply either, so:

- the bank account adapter's `canPush()` returns `false`, and the engine leaves local changes and
  deletions in `STATE_UNCONFIRMED` instead of pushing them;
- `Model\Admin\BankAccountHandler` sends them from the form, through
  `Model\BankAccount\Publisher`, with the confirmation the merchant entered
  (`Model\BankAccount\Confirmation`), then records the result with `SyncEngine::pushed()`;
- `Publisher::isPinLocked()` tells the form which kind of confirmation to ask for, and
  `Publisher::requestCode()` sends the code.

The PIN, two-factor token and code are never stored, logged, kept in the form's persisted data
(`Controller\Adminhtml\MasterData\AbstractSave::getTransientFields()`) or put into events.

Pulling bank accounts works as for the others.

Next: [Cash on delivery payouts](cash-on-delivery.md)
