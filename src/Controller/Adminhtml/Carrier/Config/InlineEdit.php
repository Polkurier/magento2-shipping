<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml\Carrier\Config;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Message\MessageInterface;
use Magento\Framework\Phrase;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Enum\PricingModelEnum;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\Shipment\PriceCalculator\Fixed;

class InlineEdit extends Action
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::carriers';

    /**
     * @param CarrierConfigRepositoryInterface $carrierConfigRepository
     * @param JsonFactory $resultJsonFactory
     * @param Context $context
     */
    public function __construct(
        private readonly CarrierConfigRepositoryInterface $carrierConfigRepository,
        private readonly JsonFactory $resultJsonFactory,
        Context $context
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultJson = $this->resultJsonFactory->create();
        $postItems = $this->getRequest()->getParam('items', []);
        if (!($this->getRequest()->getParam('isAjax') && \count($postItems))) {
            return $resultJson->setData([
                'messages' => [\__('Please correct the data sent.')],
                'error' => true,
            ]);
        }
        foreach ($postItems as $configId => $configData) {
            $carrierConfig = $this->carrierConfigRepository->findOneById((int)$configId);
            if ($carrierConfig === null) {
                $this->addError(\__('Carrier config #$1 not found', $configId));
                continue;
            }
            $this->updateCarrierConfig($carrierConfig, $configData);
            $this->carrierConfigRepository->save($carrierConfig);
        }

        return $resultJson->setData([
            'messages' => $this->getErrorMessages(),
            'error' => $this->isErrorExists(),
        ]);
    }

    /**
     * @param CarrierConfigInterface $carrierConfig
     * @param array $carrierConfigData
     *
     * @return void
     */
    private function updateCarrierConfig(CarrierConfigInterface $carrierConfig, array $carrierConfigData): void
    {
        $carrierConfig->setSortOrder((int)$carrierConfigData[CarrierConfig::FIELD_SORT_ORDER]);
        $carrierConfig->setCustomLabel(
            $carrierConfigData[CarrierConfig::FIELD_CUSTOM_LABEL] ?: null
        );
        $carrierConfig->setActive(
            (bool)$carrierConfigData[CarrierConfig::FIELD_ACTIVE]
        );
        $carrierConfig->setPricingModel(
            PricingModelEnum::from($carrierConfigData[CarrierConfig::FIELD_PRICING_MODEL])
        );
        if ($carrierConfig->getPricingModel() === PricingModelEnum::FIXED) {
            $carrierConfig->setPriceInfo(
                [Fixed::PRICE_INFO_PRICE => (float)$carrierConfigData[Fixed::PRICE_INFO_PRICE]]
            );
        }
    }

    /**
     * Get array with errors
     *
     * @return string[]
     */
    private function getErrorMessages(): array
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
    private function isErrorExists(): bool
    {
        return (bool)$this->getMessageManager()->getMessages(true)
            ->getCountByType(MessageInterface::TYPE_ERROR);
    }

    /**
     * @param Phrase $message
     *
     * @return void
     */
    private function addError(Phrase $message): void
    {
        $this->getMessageManager()->addErrorMessage($message);
    }
}
