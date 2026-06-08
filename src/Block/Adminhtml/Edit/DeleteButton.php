<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\Edit;

class DeleteButton extends GenericButton
{
    /**
     * @return array
     */
    public function getButtonData(): array
    {
        return $this->getId()
            ? [
                'label' => \__('Delete'),
                'class' => 'delete',
                'sort_order' => 20,
                'on_click' => sprintf(
                    "deleteConfirm('%s', '%s')",
                    \__('Are you sure you want to delete this?'),
                    $this->getUrl('*/*/delete', ['id' => $this->getId()])
                ),
            ]
            : [];
    }
}
