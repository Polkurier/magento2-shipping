<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment\Placement;

use Magento\Framework\App\Request\DataPersistorInterface;
use Polkurier\Shipping\Enum\FormMemoryEnum;

/**
 * The shipment form as it was sent when placing it failed, kept in the admin session so the form
 * opens again with what the merchant entered rather than the defaults.
 */
readonly class RejectedDraftStorage
{
    public function __construct(
        private DataPersistorInterface $dataPersistor,
    ) {}

    /**
     * @param array<string, mixed> $data the form's data, with its order_id
     */
    public function keep(array $data): void
    {
        $this->dataPersistor->set(FormMemoryEnum::Shipment->value, $data);
    }

    /**
     * The form kept for the order, once: it's forgotten as it's handed out.
     *
     * @return array<string, mixed>|null
     */
    public function take(int $orderId): ?array
    {
        $data = $this->dataPersistor->get(FormMemoryEnum::Shipment->value);
        if (!\is_array($data) || (int)($data['order_id'] ?? 0) !== $orderId) {
            return null;
        }
        $this->dataPersistor->clear(FormMemoryEnum::Shipment->value);

        return $data;
    }
}
