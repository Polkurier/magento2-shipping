<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Provider\Shipment;

use Magento\Sales\Api\Data\OrderInterface;
use Polkurier\Shipping\Api\BankAccountRepositoryInterface;
use Polkurier\Shipping\Api\Data\Order\CodInterface;
use Polkurier\Shipping\Api\Data\Order\CodInterfaceFactory;
use Polkurier\Shipping\Model\Provider\ConfigProvider;
use Polkurier\Webservice\Enum\CodReturnTypeEnum;
use Polkurier\Webservice\Enum\CodStatusEnum;

readonly class DefaultCodProvider
{
    /**
     * @param CodInterfaceFactory $codFactory
     * @param BankAccountRepositoryInterface $bankAccountRepository
     * @param ConfigProvider $configProvider
     */
    public function __construct(
        /**
         * @var CodInterfaceFactory
         */
        private CodInterfaceFactory $codFactory,

        /**
         * @var BankAccountRepositoryInterface
         */
        private BankAccountRepositoryInterface $bankAccountRepository,

        /**
         * @var ConfigProvider
         */
        private ConfigProvider $configProvider,
    ) {}

    /**
     * @param OrderInterface|null $order
     *
     * @return CodInterface
     */
    public function createDefaultCod(?OrderInterface $order = null): CodInterface
    {
        $cod = $this->codFactory->create();
        if ($order) {
            $cod->setAmount($order->getGrandTotal());
        }
        $cod->setType($this->configProvider->getDefaultCodType());
        $cod->setReturnType($this->configProvider->getDefaultCodRefundType());
        $cod->setStatus(CodStatusEnum::PAYMENT_PENDING);
        if ($cod->getReturnType() === CodReturnTypeEnum::BANK_ACCOUNT
            && $defaultAccount = $this->bankAccountRepository->findOneDefault()
        ) {
            $cod->setBankAccountId($defaultAccount->getId());
            $cod->setBankAccount($defaultAccount->getNumber());
        }

        return $cod;
    }
}
