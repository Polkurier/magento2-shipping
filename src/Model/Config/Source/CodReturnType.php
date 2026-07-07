<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Webservice\Enum\CodReturnTypeEnum;


readonly class CodReturnType implements OptionSourceInterface
{
    /**
     * @return array[]
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => CodReturnTypeEnum::BANK_ACCOUNT->value, 'label' => \__('Refund to a bank account')],
            ['value' => CodReturnTypeEnum::POST_OFFICE->value, 'label' => \__('Refund via postal order')],
            ['value' => CodReturnTypeEnum::MONEY_BOX->value, 'label' => \__('Refund to the money box')],
        ];
    }
}
