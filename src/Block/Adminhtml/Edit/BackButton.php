<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\Edit;

class BackButton extends GenericButton
{
    /**
     * @return array
     */
    public function getButtonData(): array
    {
        return [
            'label' => \__('Back'),
            'on_click' => sprintf(
                "location.href = '%s';",
                $this->getUrl('*/*/')
            ),
            'class' => 'back',
            'sort_order' => 10,
        ];
    }
}
