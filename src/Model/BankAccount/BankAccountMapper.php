<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\BankAccount;

use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Webservice\Builder\BankAccount\SaveMessageBuilderFactory;
use Polkurier\Webservice\Request\BankAccount\DeleteBankAccountMessage;
use Polkurier\Webservice\Request\BankAccount\SaveMessage;
use Polkurier\Webservice\Response\BankAccount;

/**
 * Maps bank accounts between Magento and the Polkurier account.
 */
readonly class BankAccountMapper
{
    public function __construct(
        private SaveMessageBuilderFactory $saveMessageBuilderFactory,
    ) {}

    /**
     * Polkurier takes a bank account only with one confirmation: the PIN when bank account edits
     * are PIN-locked, otherwise a confirmed two-factor token and its code.
     */
    public function toSaveMessage(
        BankAccountInterface $account,
        ?string $twoFactorToken = null,
        ?string $twoFactorCode = null,
        ?string $pin = null,
    ): SaveMessage {
        return $this->saveMessageBuilderFactory->create()
            ->name($account->getName())
            ->number(self::normalizeNumber($account->getNumber()))
            ->id($account->getExtId())
            ->default($account->isDefault())
            ->twoFactorToken($pin ? null : ($twoFactorToken ?: null))
            ->twoFactorCode($pin ? null : ($twoFactorCode ?: null))
            ->accessPin($pin ?: null)
            ->build();
    }

    public function toDeleteMessage(BankAccountInterface $account, ?string $pin = null): DeleteBankAccountMessage
    {
        return new DeleteBankAccountMessage((string)$account->getExtId(), $pin ?: null);
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(BankAccount $account): array
    {
        return [
            BankAccountInterface::FIELD_EXT_ID => $account->getId(),
            BankAccountInterface::FIELD_NAME => $account->getName(),
            BankAccountInterface::FIELD_NUMBER => $account->getNumber(),
            BankAccountInterface::FIELD_IS_DEFAULT => $account->isDefault(),
        ];
    }

    /**
     * @param array<string, mixed> $remote
     */
    public function apply(array $remote, BankAccountInterface $account): void
    {
        $extId = $remote[BankAccountInterface::FIELD_EXT_ID] ?? null;
        $account->setExtId($extId !== null ? (string)$extId : null)
            ->setName((string)$remote[BankAccountInterface::FIELD_NAME])
            ->setNumber((string)$remote[BankAccountInterface::FIELD_NUMBER])
            ->setDefault((bool)$remote[BankAccountInterface::FIELD_IS_DEFAULT]);
    }

    /**
     * A bank account number as Polkurier takes it: without the spaces it's usually typed with.
     */
    public static function normalizeNumber(string $typed): string
    {
        return (string)\preg_replace('/\s+/', '', $typed);
    }
}
