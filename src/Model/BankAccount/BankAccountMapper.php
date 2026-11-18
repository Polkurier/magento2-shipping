<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\BankAccount;

use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Webservice\Builder\Entity\BankAccountBuilderFactory;
use Polkurier\Webservice\Builder\Request\BankAccount\SaveBankAccountMessageBuilderFactory;
use Polkurier\Webservice\Dto\Request\BankAccount\DeleteBankAccountMessage;
use Polkurier\Webservice\Dto\Request\BankAccount\SaveBankAccountMessage;
use Polkurier\Webservice\Entity\BankAccount;

/**
 * Maps bank accounts between Magento and the Polkurier account.
 */
readonly class BankAccountMapper
{
    public function __construct(
        private BankAccountBuilderFactory $bankAccountBuilderFactory,
        private SaveBankAccountMessageBuilderFactory $saveBankAccountMessageBuilderFactory,
    ) {}

    /**
     * Polkurier takes a bank account only with one confirmation: the PIN when bank account edits
     * are PIN-locked, otherwise a confirmed two-factor token and its code.
     */
    public function mapToSaveMessage(
        BankAccountInterface $account,
        ?string $twoFactorToken = null,
        ?string $twoFactorCode = null,
        ?string $pin = null,
    ): SaveBankAccountMessage {
        return $this->saveBankAccountMessageBuilderFactory->create()
            ->setBankAccount(
                $this->bankAccountBuilderFactory->create()
                    ->setName($account->getName())
                    ->setNumber(self::normalizeNumber($account->getNumber()))
                    ->setId($account->getExtId())
                    ->setDefault($account->isDefault())
                    ->build(),
            )
            ->setTwoFactorToken($pin ? null : ($twoFactorToken ?: null))
            ->setTwoFactorCode($pin ? null : ($twoFactorCode ?: null))
            ->setAccessPin($pin ?: null)
            ->build();
    }

    public function mapToDeleteMessage(BankAccountInterface $account, ?string $pin = null): DeleteBankAccountMessage
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
