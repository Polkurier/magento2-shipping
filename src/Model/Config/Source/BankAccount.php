<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\BankAccountInterface;
use Polkurier\Shipping\Helper\Formatter;

readonly class BankAccount implements OptionSourceInterface
{
    /**
     * @param BankAccountRepositoryInterface $bankAccountRepository
     * @param Formatter $formatter
     */
    public function __construct(
        /**
         * @var BankAccountRepositoryInterface
         */
        private BankAccountRepositoryInterface $bankAccountRepository,

        /**
         * @var Formatter
         */
        private Formatter $formatter,
    ) {}

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return \array_map(
            fn (BankAccountInterface $account) => [
                'value' => $account->getAccountId(),
                'label' => $this->formatter->formatBankAccountForSelect($account),
            ],
            $this->bankAccountRepository->findAll()->getItems()
        );
    }
}
