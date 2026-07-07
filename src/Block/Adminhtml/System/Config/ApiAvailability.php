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
use Magento\Framework\Phrase;
use Magento\Framework\View\Helper\SecureHtmlRenderer;
use Polkurier\Shipping\Model\Provider\ConfigProvider;
use Polkurier\Webservice\Api\UtilsServiceInterface;
use Polkurier\Webservice\Exception\AuthorizationException;
use Polkurier\Webservice\Exception\ConnectionException;

class ApiAvailability extends Field
{
    /**
     * @var string
     */
    protected $_template = 'Polkurier_Shipping::system/config/api_availability.phtml';

    /**
     * @param UtilsServiceInterface $utilsService
     * @param ConfigProvider $configProvider
     * @param Context $context
     * @param array $data
     * @param SecureHtmlRenderer|null $secureRenderer
     */
    public function __construct(
        /**
         * @var UtilsServiceInterface
         */
        private readonly UtilsServiceInterface $utilsService,

        /**
         * @var ConfigProvider
         */
        private readonly ConfigProvider $configProvider,

        Context $context,
        array $data = [],
        ?SecureHtmlRenderer $secureRenderer = null
    ) {
        parent::__construct($context, $data, $secureRenderer);
    }

    /**
     * @param AbstractElement $element
     *
     * @return string
     */
    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseStoreValue();

        return $this->_decorateRowHtml($element, $this->_toHtml());
    }

    /**
     * @param AbstractElement $element
     * @param string $html
     *
     * @return string
     */
    protected function _decorateRowHtml(AbstractElement $element, $html): string
    {
        return '<tr id="row_' . $element->getHtmlId() . '"><td colspan="4">' . $html . '</td></tr>';
    }

    /**
     * @return Phrase|null
     */
    public function getConnectionError(): ?Phrase
    {
        try {
            $this->utilsService->testAuthApi($this->configProvider->getConfig()->getAuthorization());

            return null;
        } catch (AuthorizationException $e) {
            return __('There is a problem with API credentials: %1', $e->getMessage());
        } catch (ConnectionException $e) {
            return __('There is a problem with API connection: %1', $e->getMessage());
        }
    }
}
