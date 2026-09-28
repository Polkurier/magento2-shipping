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
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\Pricing\Fixed;
use Polkurier\Shipping\Model\Pricing\PricingModelEnum;

/**
 * Label, visibility and order edited straight in the carriers grid.
 */
class InlineEdit extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::carriers';

    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CarrierConfigRepositoryInterface $repository,
    ) {
        parent::__construct($context);
    }

    public function execute(): Json
    {
        $messages = [];
        foreach ((array)$this->getRequest()->getParam('items', []) as $id => $row) {
            try {
                $config = $this->repository->get((int)$id);
                if (\array_key_exists('custom_label', $row)) {
                    $config->setCustomLabel(\trim((string)$row['custom_label']) ?: null);
                }
                if (\array_key_exists('active', $row)) {
                    $config->setActive((bool)$row['active']);
                }
                if (\array_key_exists('sort_order', $row)) {
                    $config->setSortOrder((int)$row['sort_order']);
                }
                if (\array_key_exists('price', $row) && (string)$row['price'] !== '') {
                    $this->setFixedPrice($config, (string)$row['price']);
                }
                $this->repository->save($config);
            } catch (LocalizedException $e) {
                $messages[] = $e->getMessage();
            }
        }

        return $this->jsonFactory->create()->setData(['messages' => $messages, 'error' => $messages !== []]);
    }

    /**
     * Only a fixed price is a single number; tiers are edited on the carrier's page.
     *
     * @throws LocalizedException
     */
    private function setFixedPrice(CarrierConfigInterface $config, string $price): void
    {
        if ($config->getPricingModel() !== PricingModelEnum::Fixed->value) {
            throw new LocalizedException(\__('%1 is priced in tiers: change its prices on the carrier\'s page.', $config->getCarrierName()));
        }
        $price = \str_replace(',', '.', \trim($price));
        if (!\is_numeric($price) || (float)$price < 0) {
            throw new LocalizedException(\__('Enter the price.'));
        }
        $config->setPriceInfo([Fixed::PRICE => (float)$price]);
    }
}
