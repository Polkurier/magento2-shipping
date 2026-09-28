<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\MasterData;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Model\Admin\SyncableEntityHandler;

/**
 * Base of the admin actions on addresses, bank accounts and pack templates. Each entity's
 * controllers pass their handler, which also names the ACL resource.
 */
abstract class AbstractAction extends Action
{
    public function __construct(
        Context $context,
        protected readonly SyncableEntityHandler $handler,
    ) {
        parent::__construct($context);
    }

    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed($this->handler->getAclResource());
    }

    /**
     * @param string[] $lines
     */
    protected function reportSync(array $lines): void
    {
        foreach ($lines as $line) {
            $this->messageManager->addNoticeMessage($line);
        }
    }
}
