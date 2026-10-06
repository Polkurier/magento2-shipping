<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\ConfigOptionsListConstants;
use Polkurier\Shipping\Model\Config;

/**
 * This Magento installation's id, sent to Polkurier with every order as its
 * external_application_id: the one entered as "Installation ID", or else "magento2-" and a code
 * made from the installation's first encryption key. Installations sharing a Polkurier account,
 * e.g. a shop and its staging copy, so tell their orders apart; rotating the key keeps the first,
 * and so the id. A copy that took the key along needs an id entered. Polkurier keeps up to 50
 * characters of it, and checks nothing about it.
 */
class ApplicationId
{
    public const string PREFIX = 'magento2-';
    public const int CODE_LENGTH = 12;
    public const int MAX_LENGTH = 50;

    /**
     * What an entered id may be: letters, digits, dots, dashes and underscores.
     */
    public const string PATTERN = '/^[A-Za-z0-9._-]{1,50}$/';

    private ?string $computed = null;

    public function __construct(
        private readonly DeploymentConfig $deploymentConfig,
        private readonly Config $config,
    ) {}

    public function get(): string
    {
        $entered = $this->config->getApplicationIdOverride();

        return $entered !== '' ? $entered : $this->computed();
    }

    /**
     * The id made from the first encryption key, whatever is entered.
     */
    public function computed(): string
    {
        if ($this->computed !== null) {
            return $this->computed;
        }
        $keys = \preg_split('/\s+/', \trim((string)$this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_CRYPT_KEY))) ?: [];
        $first = (string)($keys[0] ?? '');

        return $this->computed = self::PREFIX . \substr(\hash('sha256', 'polkurier-application:' . $first), 0, self::CODE_LENGTH);
    }
}
