<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;

/**
 * An admin action that screens call in the background. It answers with JSON holding "error" and,
 * when that is true, "message": the refusal, in Polkurier's or the module's words.
 */
abstract class AbstractJsonAction extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        protected readonly JsonFactory $jsonFactory,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        try {
            $data = \array_replace(['error' => false], $this->answer());
        } catch (LocalizedException $e) {
            $data = ['error' => true, 'message' => $e->getMessage()] + $this->refused($e);
        }

        return $this->jsonFactory->create()->setData($data);
    }

    /**
     * The answer; "error" is false unless it says otherwise.
     *
     * @return array<string, mixed>
     * @throws LocalizedException to answer with an error carrying its message
     */
    abstract protected function answer(): array;

    /**
     * What an error answer carries besides its message.
     *
     * @return array<string, mixed>
     */
    protected function refused(LocalizedException $e): array
    {
        return [];
    }
}
