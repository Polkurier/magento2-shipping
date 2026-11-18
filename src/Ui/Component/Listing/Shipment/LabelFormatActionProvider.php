<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Shipment;

use Magento\Framework\UrlInterface;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Label as ShipmentLabel;
use Polkurier\Shipping\Model\Config\Source\LabelFormat;

/**
 * The submenu of the "Print labels" mass action: one entry per label format, PDF first.
 */
readonly class LabelFormatActionProvider implements \JsonSerializable
{
    public function __construct(
        private UrlInterface $url,
        private LabelFormat $labelFormat,
    ) {}

    /**
     * @return array<int, array{type: string, label: string, url: string}>
     */
    public function jsonSerialize(): array
    {
        $actions = [];
        foreach ($this->labelFormat->toArray() as $format => $name) {
            $actions[] = [
                'type' => 'label_' . \strtolower($format ?: 'pdf'),
                'label' => (string)$name,
                'url' => $this->url->getUrl(
                    ShipmentLabel::ROUTE,
                    $format !== '' ? ['format' => $format] : [],
                ),
            ];
        }

        return $actions;
    }
}
