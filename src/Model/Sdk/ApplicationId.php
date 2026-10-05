<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\ConfigOptionsListConstants;

/**
 * This Magento installation's id, sent to Polkurier with every order as its
 * external_application_id: "magento2-" and a code made from the installation's first encryption
 * key. Installations sharing a Polkurier account, e.g. a shop and its staging copy with a key of
 * its own, so tell their orders apart; rotating the key keeps the first, and so the id. Polkurier
 * keeps up to 50 characters of it, and checks nothing about it.
 */
class ApplicationId
{
    public const string PREFIX = 'magento2-';
    public const int CODE_LENGTH = 12;

    private ?string $id = null;

    public function __construct(
        private readonly DeploymentConfig $deploymentConfig,
    ) {}

    public function get(): string
    {
        if ($this->id !== null) {
            return $this->id;
        }
        $keys = \preg_split('/\s+/', \trim((string)$this->deploymentConfig->get(ConfigOptionsListConstants::CONFIG_PATH_CRYPT_KEY))) ?: [];
        $first = (string)($keys[0] ?? '');

        return $this->id = self::PREFIX . \substr(\hash('sha256', 'polkurier-application:' . $first), 0, self::CODE_LENGTH);
    }
}
