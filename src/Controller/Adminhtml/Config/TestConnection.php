<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Config;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Http\Request\AuthorizationMessage;

/**
 * Checks credentials as typed in the configuration form, before they are saved.
 */
class TestConnection extends AbstractJsonAction
{
    public const string ADMIN_RESOURCE = 'Magento_Shipping::carriers';

    /**
     * What an obscured field shows instead of a saved value.
     */
    private const string MASK = '******';

    public function __construct(
        Context $context,
        JsonFactory $jsonFactory,
        private readonly ClientPool $clientPool,
        private readonly Config $config,
        private readonly ErrorMessages $errorMessages,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $login = \trim((string)$this->getRequest()->getParam('customer_id'));
        $token = \trim((string)$this->getRequest()->getParam('api_token'));
        $login = $login === '' || $login === self::MASK ? $this->config->getLogin() : $login;
        $token = $token === '' || $token === self::MASK ? $this->config->getToken() : $token;
        if ($login === '' || $token === '') {
            throw new LocalizedException(\__('Enter the customer ID and the API token.'));
        }
        try {
            $this->clientPool->utils()->testAuthApi(new AuthorizationMessage(token: $token, login: $login));
        } catch (PolkurierException $e) {
            throw $this->errorMessages->exception($e);
        }

        return ['message' => (string)\__('Polkurier accepted the credentials.')];
    }
}
