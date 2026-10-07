<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Polkurier\Shipping\Model\Admin\FlashMessageCollector;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;
use Polkurier\Shipping\Model\Sync\EntityHandlerInterface;
use Polkurier\Shipping\Model\Sync\SyncResult;

/**
 * Base of the admin actions on addresses, bank accounts and pack templates. Each entity's
 * controllers pass their handler and declare their ACL resource.
 */
abstract class AbstractAction extends Action
{
    use ReturnsBackTrait;

    public function __construct(
        Context $context,
        protected readonly EntityHandlerInterface $handler,
        protected readonly FlashMessageCollectorFactory $flashMessageCollectorFactory,
    ) {
        parent::__construct($context);
    }

    /**
     * A full synchronization's lines, one per kind, as the action's one message.
     *
     * @param string[] $lines
     */
    protected function reportSync(array $lines): void
    {
        $flash = $this->flashMessageCollectorFactory->create();
        foreach ($lines as $line) {
            $flash->notice($line);
        }
        $flash->send($this->messageManager);
    }

    /**
     * Polkurier's reasons, once each.
     */
    protected function reasons(SyncResult $result): string
    {
        return \implode(' ', \array_unique($result->errors));
    }
}
