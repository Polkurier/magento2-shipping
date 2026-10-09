<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Polkurier\Shipping\Model\Sdk\ApplicationIdProvider;

/**
 * "Installation ID" in the Polkurier configuration. While the field is empty, it shows the id made
 * from the encryption key as a placeholder, not as a value. A saved value would travel with a copied
 * database to a staging copy, and the id is there to tell that copy's orders apart.
 */
class InstallationId extends Field
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly ApplicationIdProvider $applicationIdProvider,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null,
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $element->setData('placeholder', $this->applicationIdProvider->computed());

        return parent::_getElementHtml($element);
    }
}
