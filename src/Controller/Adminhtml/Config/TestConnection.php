<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Config;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Controller\Adminhtml\AbstractJsonAction;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\RequestParam;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Webservice\Exception\AuthorizationException;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Http\Request\AuthorizationMessageFactory;

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
        private readonly PolkurierClientInterface $polkurierClient,
        private readonly ConfigProvider $configProvider,
        private readonly ErrorMessageResolver $errorMessageResolver,
        private readonly AuthorizationMessageFactory $authorizationMessageFactory,
    ) {
        parent::__construct($context, $jsonFactory);
    }

    protected function answer(): array
    {
        $login = \trim(RequestParam::string($this->getRequest(), 'customer_id'));
        $token = \trim(RequestParam::string($this->getRequest(), 'api_token'));
        $login = $login === '' || $login === self::MASK ? $this->configProvider->getLogin() : $login;
        $token = $token === '' || $token === self::MASK ? $this->configProvider->getToken() : $token;
        if ($login === '' || $token === '') {
            throw new LocalizedException(__('Enter the customer ID and the API token.'));
        }
        try {
            $this->polkurierClient->utils()->testAuthApi(
                $this->authorizationMessageFactory->create(['token' => $token, 'login' => $login]),
            );
        } catch (AuthorizationException $e) {
            // Shown next to the fields: the usual message's way to them would lead back here.
            throw new LocalizedException(__(
                'Polkurier did not accept this customer ID and API token. (%1)',
                $e->getErrorMessage()->getResponse(),
            ), $e);
        } catch (PolkurierException $e) {
            throw $this->errorMessageResolver->exception($e);
        }

        return ['message' => (string)__('Polkurier accepted the credentials.')];
    }
}
