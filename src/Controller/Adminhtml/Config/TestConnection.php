<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Http\Request\AuthorizationMessage;

/**
 * Checks credentials as typed in the configuration form, before they are saved.
 */
class TestConnection extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Magento_Shipping::carriers';

    /**
     * What an obscured field shows instead of a saved value.
     */
    private const string MASK = '******';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ClientPool $clientPool,
        private readonly Config $config,
        private readonly ErrorMessages $errorMessages,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $login = \trim((string)$this->getRequest()->getParam('customer_id'));
        $token = \trim((string)$this->getRequest()->getParam('api_token'));
        $login = $login === '' || $login === self::MASK ? $this->config->getLogin() : $login;
        $token = $token === '' || $token === self::MASK ? $this->config->getToken() : $token;

        if ($login === '' || $token === '') {
            return $this->jsonFactory->create()->setData([
                'success' => false,
                'message' => (string)\__('Enter the customer ID and the API token.'),
            ]);
        }

        try {
            $this->clientPool->utils()->testAuthApi(new AuthorizationMessage(token: $token, login: $login));
            $result = ['success' => true, 'message' => (string)\__('Polkurier accepted the credentials.')];
        } catch (PolkurierException $e) {
            $result = ['success' => false, 'message' => $this->errorMessages->describe($e)];
        }

        return $this->jsonFactory->create()->setData($result);
    }
}
