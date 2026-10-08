<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Ui\Component\Control\Container;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Config\Source\LabelFormat;
use Polkurier\Shipping\Ui\Component\Control\OnClickScriptBuilder;

/**
 * Prints the shipment's label: a PDF, or, from the button's list, in a label printer's language.
 */
class LabelButton extends AbstractButton
{
    public function __construct(
        RequestInterface $request,
        UrlInterface $url,
        AuthorizationInterface $authorization,
        ShipmentRepositoryInterface $shipmentRepository,
        OnClickScriptBuilder $onClickScriptBuilder,
        private readonly LabelFormat $labelFormat,
    ) {
        parent::__construct($request, $url, $authorization, $shipmentRepository, $onClickScriptBuilder);
    }

    /**
     * @return array<string, mixed>
     */
    public function getButtonData(): array
    {
        if (!$this->isCreated()) {
            return [];
        }
        $options = [];
        foreach ($this->labelFormat->toArray() as $format => $name) {
            $options[] = [
                'id' => 'format-' . \strtolower($format ?: 'pdf'),
                'label' => $name,
                'onclick' => $this->onClickScriptBuilder->location(
                    $this->actionUrl('label', $format !== '' ? ['format' => $format] : []),
                ),
                // What the button itself prints.
                'default' => $format === '',
            ];
        }

        return [
            'label' => (string)__('Print label'),
            'class' => 'primary',
            'class_name' => Container::SPLIT_BUTTON,
            'options' => $options,
            'sort_order' => 90,
        ];
    }
}
