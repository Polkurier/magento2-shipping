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
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\CarrierLabelInterface;
use Polkurier\Shipping\Api\Data\CarrierLabelInterfaceFactory;
use Polkurier\Shipping\Model\Carrier\CarrierLogo;
use Polkurier\Shipping\Model\Pricing\Fixed;
use Polkurier\Shipping\Model\Pricing\PricingModelEnum;
use Polkurier\Shipping\Model\Pricing\Tiers;

/**
 * Saves how a carrier is offered and priced in checkout.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const string ADMIN_RESOURCE = 'Polkurier_Shipping::carriers';

    public function __construct(
        Context $context,
        private readonly CarrierConfigRepositoryInterface $repository,
        private readonly CarrierLabelInterfaceFactory $labelFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CarrierLogo $carrierLogo,
    ) {
        parent::__construct($context);
    }

    public function execute(): Redirect
    {
        $data = $this->getPostData();
        $id = (int)($data['id'] ?? 0);
        $redirect = $this->resultRedirectFactory->create();
        try {
            $model = PricingModelEnum::tryFrom((string)($data['pricing_model'] ?? '')) ?? PricingModelEnum::Fixed;
            $config = $this->repository->get($id);
            $config->setCustomLabel(\trim((string)($data['custom_label'] ?? '')) ?: null)
                ->setLogo($this->logo($data))
                ->setStoreLabels($this->storeLabels($config, $data))
                ->setActive((bool)($data['active'] ?? false))
                ->setSortOrder((int)($data['sort_order'] ?? 0))
                ->setPricingModel($model->value)
                ->setPriceInfo($this->priceInfo($model, $data));
            $this->repository->save($config);
            $this->messageManager->addSuccessMessage(\__('Saved.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('*/*/edit', ['id' => $id]);
        }

        return $this->getRequest()->getParam('back')
            ? $redirect->setPath('*/*/edit', ['id' => $id])
            : $redirect->setPath('*/*/index');
    }

    /**
     * The image uploader posts the file it holds, or nothing once it's removed.
     *
     * @param array<string, mixed> $data
     */
    private function logo(array $data): ?string
    {
        $file = (string)($data['logo'][0]['file'] ?? '');

        return $file !== '' && $this->carrierLogo->isOwnFile($file) ? $file : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     * @throws LocalizedException
     */
    private function priceInfo(PricingModelEnum $model, array $data): array
    {
        if ($model === PricingModelEnum::Fixed) {
            $price = \str_replace(',', '.', (string)($data[Fixed::PRICE] ?? ''));
            if (!\is_numeric($price) || (float)$price < 0) {
                throw new LocalizedException(\__('Enter the price.'));
            }

            return [Fixed::PRICE => (float)$price];
        }

        $tiers = [];
        foreach ((array)($data[Tiers::TIERS] ?? []) as $row) {
            if (!\is_array($row) || !empty($row['delete']) || !\is_numeric(\str_replace(',', '.', (string)($row[Tiers::PRICE] ?? '')))) {
                continue;
            }
            $tiers[] = [
                Tiers::SUBTOTAL_FROM => (float)\str_replace(',', '.', (string)($row[Tiers::SUBTOTAL_FROM] ?? 0)),
                Tiers::WEIGHT_FROM => (float)\str_replace(',', '.', (string)($row[Tiers::WEIGHT_FROM] ?? 0)),
                Tiers::PRICE => (float)\str_replace(',', '.', (string)$row[Tiers::PRICE]),
            ];
        }
        if ($tiers === []) {
            throw new LocalizedException(\__('Add at least one price tier.'));
        }

        return [Tiers::TIERS => $tiers];
    }

    /**
     * Store view labels from the form's rows; empty ones and store views of other websites are
     * dropped, and a store view listed twice keeps its last label.
     *
     * @param array<string, mixed> $data
     * @return CarrierLabelInterface[]
     */
    private function storeLabels(CarrierConfigInterface $config, array $data): array
    {
        $labels = [];
        foreach ((array)($data[CarrierConfigInterface::STORE_LABELS] ?? []) as $row) {
            $storeId = (int)($row[CarrierLabelInterface::STORE_ID] ?? 0);
            $label = \trim((string)($row[CarrierLabelInterface::LABEL] ?? ''));
            if ($storeId === 0 || $label === '' || !$this->belongsToWebsite($storeId, $config->getWebsiteId())) {
                continue;
            }
            $labels[$storeId] = $this->labelFactory->create()->setStoreId($storeId)->setLabel($label);
        }

        return \array_values($labels);
    }

    private function belongsToWebsite(int $storeId, int $websiteId): bool
    {
        foreach ($this->storeManager->getStores() as $store) {
            if ((int)$store->getId() === $storeId) {
                return (int)$store->getWebsiteId() === $websiteId;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function getPostData(): array
    {
        $request = $this->getRequest();

        return $request instanceof \Magento\Framework\App\Request\Http ? (array)$request->getPostValue() : [];
    }
}
