<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\BankAccount;

/**
 * What the merchant gave to confirm a bank account change: the PIN, or a two-factor token and code.
 * Never stored.
 */
final readonly class Confirmation
{
    public function __construct(
        public ?string $pin = null,
        public ?string $twoFactorToken = null,
        public ?string $twoFactorCode = null,
    ) {}

    public function isGiven(): bool
    {
        return $this->pin !== null || ($this->twoFactorToken !== null && $this->twoFactorCode !== null);
    }

    /**
     * @param array<string, mixed> $data the form's fields
     */
    public static function fromForm(array $data): self
    {
        $value = static fn(string $key): ?string => \trim((string)($data[$key] ?? '')) ?: null;

        return new self(
            $value(BankAccountHandler::FIELD_CONFIRMATION_PIN),
            $value('two_factor_token'),
            $value('two_factor_code'),
        );
    }
}
