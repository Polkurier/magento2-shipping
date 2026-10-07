<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * "Test connection" in the Polkurier configuration: checks the credentials as typed, before they
 * are saved.
 *
 * System configuration fields have no UI component equivalent, so this is a frontend model with a
 * small template; the behaviour lives in a JS module.
 */
class TestConnection extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Polkurier_Shipping::system/config/test_connection.phtml';

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();

        return parent::render($element);
    }

    public function getButtonLabel(): string
    {
        return (string)__('Test connection');
    }

    public function getTestUrl(): string
    {
        return $this->getUrl('polkurier/config/testConnection');
    }

    /**
     * The ids of the customer ID and API token fields, which sit next to this button's field.
     *
     * @return array{customer: string, token: string}
     */
    public function getFieldIds(): array
    {
        $prefix = (string)$this->getData('field_prefix');

        return ['customer' => $prefix . 'customer_id', 'token' => $prefix . 'api_token'];
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        // The button's own id ends in its field name; the others share the rest.
        $id = (string)$element->getHtmlId();
        $this->setData(
            'field_prefix',
            \str_ends_with($id, 'test_connection') ? \substr($id, 0, -\strlen('test_connection')) : '',
        );

        return $this->_toHtml();
    }
}
