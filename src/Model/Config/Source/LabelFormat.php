<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Polkurier\Webservice\Enum\Order\LabelFileFormatEnum;

/**
 * The formats labels are printed in: PDF, which the API returns when none is asked for, or the
 * language of a label printer.
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
