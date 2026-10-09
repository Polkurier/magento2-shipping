<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\MassAction;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Ui\Component\Action;

/**
 * A mass action shown only to admins allowed to run it: its "aclResource" argument, in the
 * listing's XML, is the ADMIN_RESOURCE of the action it posts to.
 */
class AllowedAction extends Action
{
    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     * @param array<mixed>|\JsonSerializable|null $actions
     */
    public function __construct(
        ContextInterface $context,
        private readonly AuthorizationInterface $authorization,
        array $components = [],
        array $data = [],
        $actions = null,
        private readonly string $aclResource = '',
    ) {
        parent::__construct($context, $components, $data, $actions);
    }

    public function prepare(): void
    {
        parent::prepare();
        if ($this->aclResource !== '' && !$this->authorization->isAllowed($this->aclResource)) {
            $this->setData('config', ['actionDisable' => true] + (array)$this->getData('config'));
        }
    }
}
