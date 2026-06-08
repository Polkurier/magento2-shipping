<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Block\Adminhtml\Edit;

use Magento\Backend\Block\Widget\Context;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

abstract class GenericButton implements ButtonProviderInterface
{
    /**
     * @param Context $context
     */
    public function __construct(
        /**
         * @var Context
         */
        private readonly Context $context,
    ) {}

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return (int)$this->context->getRequest()->getParam('id') ?: null;
    }

    /**
     * @param string $route
     * @param array $params
     *
     * @return string
     */
    public function getUrl(string $route, array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
