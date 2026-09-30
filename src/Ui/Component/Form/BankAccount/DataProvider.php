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
use Polkurier\Shipping\Model\BankAccount\Publisher;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Shipping\Ui\Component\Form\MasterData\DataProvider as MasterDataProvider;

/**
 * The bank account form, plus what its confirmation fields need: whether the account asks for
 * the security PIN instead of a code, and where to ask for a code.
 */
class DataProvider extends MasterDataProvider
{
    /**
     * How long whether bank account changes need the PIN is trusted, in seconds.
     */
    public const int PIN_LOCK_LIFETIME = 900;

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
        private readonly AccountCache $accountCache,
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
            $result[$key]['pin_locked'] = $this->isPinLocked();
            $result[$key]['urls'] = ['send_code' => $this->url->getUrl('polkurier/bankaccount/sendCode')];
        }

        return $result;
    }

    private function isPinLocked(): bool
    {
        try {
            // Rarely changed, and asked for with every form opened.
            return (bool)$this->accountCache->remember('pin_locked', self::PIN_LOCK_LIFETIME, fn(): bool => $this->publisher->isPinLocked());
        } catch (LocalizedException) {
            // Unknown: offer the code, which Polkurier then refuses with its own reason if a PIN is needed.
            return false;
        }
    }
}
