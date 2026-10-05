<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Mapper;

use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Webservice\Request\BankAccount\DeleteBankAccountMessage;
use Polkurier\Webservice\Request\BankAccount\SaveMessage;
use Polkurier\Webservice\Response\BankAccount;

/**
 * Maps bank accounts between Magento and the Polkurier account.
 */
readonly class BankAccountMapper
{
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
        return new SaveMessage(
            name: $account->getName(),
            number: \preg_replace('/\s+/', '', $account->getNumber()) ?? $account->getNumber(),
            id: $account->getExtId(),
            default: $account->isDefault(),
            twoFactorToken: $pin ? null : ($twoFactorToken ?: null),
            twoFactorCode: $pin ? null : ($twoFactorCode ?: null),
            accessPin: $pin ?: null,
        );
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
            'id' => $account->getId(),
            'name' => $account->getName(),
            'number' => $account->getNumber(),
            'default' => $account->isDefault(),
        ];
    }

    /**
     * @param array<string, mixed> $remote
     */
    public function apply(array $remote, BankAccountInterface $account): void
    {
        $account->setExtId(isset($remote['id']) ? (string)$remote['id'] : null)
            ->setName((string)$remote['name'])
            ->setNumber((string)$remote['number'])
            ->setDefault((bool)$remote['default']);
    }
}
