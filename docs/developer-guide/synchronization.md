<!--
 ~ Copyright (c) 2026 Polkurier.pl
 ~ Licensed under the MIT License, see LICENSE.md
-->
# Synchronization

[Developer guide](README.md) › Synchronization

Addresses, bank accounts and pack templates exist both locally and on the Polkurier account, and
the merchant can edit them on either side. The synchronization brings the two sides in line, one
kind of entry at a time. The kinds are fixed:
[`Enum\Sync\SyncEntityEnum`](../../src/Enum/Sync/SyncEntityEnum.php) lists `Address`,
`BankAccount` and `PackTemplate`.

To run it from code, call [`Model\Sync\Synchronization`](../../src/Model/Sync/Synchronization.php)
with the kinds you want, or none for all:

```php
$this->synchronization->synchronize([SyncEntityEnum::Address, SyncEntityEnum::PackTemplate]);
```

It returns one summary line per kind. Only one writer at a time changes the synchronized entries,
so a call that can't get its turn within 20 seconds throws a
`Magento\Framework\Exception\LocalizedException` asking to try again.

## How the engine decides

For each entry, the engine compares both sides with how the last sync left them. The local row
keeps a hash of the remote entry (`remote_hash`) and the time of the last sync (`synced_at`). The
local side counts as changed when the row is `pending`, `error` or `unconfirmed`, or when
`updated_at` is later than `synced_at`. A row deleted locally is soft-deleted first (`deleted_at`),
so the delete can be pushed.

```
Address, then BankAccount, then PackTemplate      SyncEngine::run() for each kind asked for
        │
read the Polkurier list and the local rows
        │
each local row, against how the last sync left it
├── not linked to a Polkurier entry
│   ├── deleted locally ........................................ row removed
│   ├── one unlinked Polkurier entry has its natural key ....... linked, then compared as a pair
│   └── otherwise .............................................. push
├── its Polkurier entry is gone
│   ├── changed locally since the last sync .................... push, as a new entry
│   └── otherwise .............................................. row removed
├── deleted locally
│   ├── changed on Polkurier after the delete .................. restored and pulled
│   └── otherwise .............................................. push the delete, row removed
├── changed on both sides ...................................... the later change wins
├── changed on Polkurier only .................................. pull
├── changed locally only ....................................... push
└── unchanged .................................................. marked synced
        │
each Polkurier entry no row is linked to ....................... pull, as a new row

push
├── Address, PackTemplate ...................................... sent to Polkurier
└── BankAccount ................................................ not sent: the row waits as
                                                                 unconfirmed for the merchant's
                                                                 code or PIN
```

The natural key is the name of an address or pack template, the number of a bank account. Matching
by it links an entry sent before whose link failed to save, so it isn't sent twice. Polkurier
doesn't report when its entries changed, so a remote change is dated to the sync that first sees it.

A failed push leaves the row in the error state, with the reason in `sync_error`; the next run tries
again. A failure of one entry or one kind doesn't stop the others; it's logged. Only when every kind
asked for fails does the run throw
[`Exception\SynchronizationFailedException`](../../src/Exception/SynchronizationFailedException.php).

## When it runs

- Saving or deleting an entry in the admin sends only that entry, straight away.
- When `sync/enabled` is on and the credentials are set,
  [`Cron\SynchronizeTask`](../../src/Cron/SynchronizeTask.php) runs the full sync hourly, and
  [`Cron\RefreshCarriersTask`](../../src/Cron/RefreshCarriersTask.php) refreshes the carrier list
  daily.
- The *Synchronize with Polkurier* button on each settings screen runs it for that kind of entry,
  once the credentials are set.

Other changes, on either side, wait for one of these runs.

## Bank accounts

Polkurier accepts a bank account change only with the account holder's confirmation. Saving needs
the security PIN when the account locks bank account changes, and otherwise a two-factor code, one
per save. Deleting needs the PIN when the account is locked. The sync can't supply either, so it
never sends bank accounts: their local changes and deletions wait as `unconfirmed` until the
merchant confirms them in the admin form. Pulling bank accounts works as for the others.

The admin form goes through
[`Model\BankAccount\BankAccountHandler`](../../src/Model/BankAccount/BankAccountHandler.php):

```
saved in the form ................................ stored as unconfirmed
├── without the PIN or a two-factor code ......... waits as unconfirmed
└── with one: Publisher::save()
    ├── accepted ................................. synced
    └── refused .................................. waits as unconfirmed, the reason in sync_error

deleted in the form .............................. Publisher::delete(), with the PIN if locked
├── accepted ..................................... row removed
└── refused ...................................... the reason is shown, the row is kept
```

[`Model\BankAccount\Publisher`](../../src/Model/BankAccount/Publisher.php) sends a bank account
change with its confirmation. `isPinLocked()` tells which kind of confirmation to ask for, and
`requestCode()` sends the two-factor code.

The PIN, two-factor token and code are never stored, logged, kept in the form's persisted data or
put into events.

Next: [Cash on delivery payouts](cash-on-delivery.md)
