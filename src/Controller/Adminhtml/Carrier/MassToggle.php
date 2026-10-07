<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Carrier;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Controller\Adminhtml\ReturnsBackTrait;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\CollectionFactory;

/**
 * Flips Enabled on the carriers chosen in the list: the enabled ones are switched off, the others on.
 */
class MassToggle extends Action implements HttpPostActionInterface
{
    use ReturnsBackTrait;

    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::carriers';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly CarrierConfigRepositoryInterface $repository,
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $enabled = 0;
        $disabled = 0;
        try {
            /** @var CarrierConfigInterface $config */
            foreach ($this->filter->getCollection($this->collectionFactory->create()) as $config) {
                $this->repository->save($config->setActive(!$config->isActive()));
                $config->isActive() ? $enabled++ : $disabled++;
            }
            $this->messageManager->addSuccessMessage(__('%1 enabled, %2 disabled.', $enabled, $disabled));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $this->returnBack('*/*/index');
    }
}
