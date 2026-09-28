<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Multishipping;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Quote\Model\Quote\Address;
use Polkurier\Shipping\Model\Checkout\MapConfigProvider;
use Polkurier\Shipping\Model\OrderPointStore;

/**
 * The pickup point picker under one address of the multi-address checkout's shipping step.
 *
 * That step is a block template, not the knockout checkout, so the picker is a template too.
 * Nothing it outputs may name Polkurier.
 */
class PickupPoint extends Template
{
    /**
     * @var string
     */
    protected $_template = 'Polkurier_Shipping::multishipping/pickup-point.phtml';

    private ?Address $address = null;

    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly MapConfigProvider $mapConfigProvider,
        private readonly OrderPointStore $pointStore,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    public function setAddress(Address $address): static
    {
        $this->address = $address;

        return $this;
    }

    /**
     * @return array<string, mixed>|null null when no method of the address can take a point
     */
    public function getWidgetConfig(): ?array
    {
        if ($this->address === null) {
            return null;
        }
        $config = $this->mapConfigProvider->getConfig()['pickupPoints'];
        if ((array)$config['methods'] === []) {
            return null;
        }
        unset($config['selected']);
        $addressId = (int)$this->address->getId();
        $point = $this->pointStore->getForQuote((int)$this->address->getQuoteId(), $addressId);

        return $config + [
            'addressId' => $addressId,
            'searchQuery' => \trim($this->address->getPostcode() . ' ' . $this->address->getCity()),
            'selected' => $point === null ? null : [
                'methodCode' => $point->getMethodCode(),
                'id' => $point->getPointId(),
                'label' => (string)($point->getPointLabel() ?: $point->getPointId()),
            ],
        ];
    }
}
