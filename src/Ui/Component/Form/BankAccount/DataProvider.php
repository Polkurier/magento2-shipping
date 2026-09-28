<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\BankAccount;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Polkurier\Shipping\Model\BankAccount\Publisher;
use Polkurier\Shipping\Model\WebsiteScope;
use Polkurier\Shipping\Ui\Component\Form\MasterData\DataProvider as MasterDataProvider;

/**
 * The bank account form, plus what its confirmation fields need: whether the account asks for
 * the security PIN instead of a code, and where to ask for a code.
 */
class DataProvider extends MasterDataProvider
{
    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        AbstractCollection $collection,
        RequestInterface $request,
        DataPersistorInterface $dataPersistor,
        private readonly UrlInterface $url,
        private readonly Publisher $publisher,
        private readonly WebsiteScope $websiteScope,
        private readonly StoreManagerInterface $storeManager,
        string $persistorKey = 'polkurier_bank_account',
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $collection, $request, $dataPersistor, $persistorKey, $meta, $data);
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function getData(): array
    {
        $result = parent::getData();
        if ($result === []) {
            $result[''] = [];
        }
        foreach ($result as $key => $item) {
            $websiteId = (int)($item['website_id'] ?? $this->storeManager->getDefaultStoreView()?->getWebsiteId());
            $result[$key]['pin_locked'] = $this->isPinLocked($websiteId);
            $result[$key]['urls'] = ['send_code' => $this->url->getUrl('polkurier/bankaccount/sendCode')];
        }

        return $result;
    }

    private function isPinLocked(int $websiteId): bool
    {
        try {
            return $this->publisher->isPinLocked($this->websiteScope->getStoreId($websiteId));
        } catch (LocalizedException) {
            // Unknown: offer the code, which Polkurier then refuses with its own reason if a PIN is needed.
            return false;
        }
    }
}
