<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\ForwardFactory;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\Phrase;
use Magento\Framework\View\Result\PageFactory;
use Psr\Log\LoggerInterface;

abstract class Index extends Action
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::root';

    /**
     * @param PageFactory $resultPageFactory
     * @param ForwardFactory $resultForwardFactory
     * @param JsonFactory $resultJsonFactory
     * @param LoggerInterface $logger
     * @param Context $context
     */
    public function __construct(
        /**
         * @var PageFactory
         */
        protected readonly PageFactory $resultPageFactory,

        /**
         * @var ForwardFactory
         */
        protected readonly ForwardFactory $resultForwardFactory,

        /**
         * @var JsonFactory
         */
        protected readonly JsonFactory $resultJsonFactory,

        /**
         * @var LoggerInterface
         */
        protected readonly LoggerInterface $logger,

        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * Get array with errors
     *
     * @return string[]
     */
    protected function getErrorMessages(): array
    {
        $messages = [];
        foreach ($this->getMessageManager()->getMessages()->getErrors() as $error) {
            $messages[] = $error->getText();
        }

        return $messages;
    }

    /**
     * Check if errors exists
     *
     * @return bool
     */
    protected function isErrorExists(): bool
    {
        return (bool)$this->getMessageManager()->getMessages(true)
            ->getCountByType(MessageInterface::TYPE_ERROR);
    }

    /**
     * @param Phrase $message
     *
     * @return void
     */
    protected function addError(Phrase $message): void
    {
        $this->getMessageManager()->addErrorMessage($message);
    }
}
