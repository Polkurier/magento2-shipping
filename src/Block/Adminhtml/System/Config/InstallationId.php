<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\System\Config;

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Polkurier\Shipping\Model\Sdk\ApplicationId;

/**
 * "Installation ID" in the Polkurier configuration: the id made from the encryption key shows in
 * the field while it's empty, as its placeholder. Not as its value, which a copied database would
 * take along to a staging copy, whose orders the id is there to tell apart.
 */
class InstallationId extends Field
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly ApplicationId $applicationId,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null,
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        $element->setData('placeholder', $this->applicationId->computed());

        return parent::_getElementHtml($element);
    }
}
