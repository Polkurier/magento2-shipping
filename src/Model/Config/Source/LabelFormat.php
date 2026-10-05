<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\Order\LabelFileFormatEnum;

/**
 * Label formats. PDF is what the API returns when none is asked for.
 */
readonly class LabelFormat extends AbstractOptions
{
    public function toArray(): array
    {
        return [
            '' => 'PDF',
            LabelFileFormatEnum::Zpl->value => 'ZPL (203 dpi)',
            LabelFileFormatEnum::Zpl300->value => 'ZPL (300 dpi)',
            LabelFileFormatEnum::Epl->value => 'EPL',
            LabelFileFormatEnum::Datamax->value => 'Datamax',
        ];
    }
}
