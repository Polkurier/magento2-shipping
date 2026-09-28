<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Shipping\Model\Sdk\ClientPool;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Request\Order\GetDocumentMessage;

/**
 * Labels and handover protocols for Polkurier orders. Several orders of one account come back as
 * one document.
 */
class Documents
{
    public function __construct(
        private readonly ClientPool $clientPool,
        private readonly Config $config,
        private readonly ErrorMessages $errorMessages,
    ) {}

    /**
     * @param ShipmentInterface[] $shipments
     * @param bool $pdf a PDF whatever label format is configured, as Magento stores labels as PDF
     * @return array{file_name: string, content: string, content_type: string}
     * @throws LocalizedException
     */
    public function getLabels(array $shipments, bool $pdf = false): array
    {
        [$storeId, $numbers] = $this->numbers($shipments);
        $format = $pdf ? null : $this->config->getLabelFormat($storeId);

        try {
            $document = $this->clientPool->orders($storeId)->getLabel(new GetDocumentMessage($numbers, $format));
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }

        return $format === null
            ? ['file_name' => $this->fileName('labels', $numbers, 'pdf'), 'content' => $document->getDecodedFile(), 'content_type' => 'application/pdf']
            : ['file_name' => $this->fileName('labels', $numbers, 'txt'), 'content' => $document->getDecodedFile(), 'content_type' => 'text/plain'];
    }

    /**
     * @param ShipmentInterface[] $shipments
     * @return array{file_name: string, content: string, content_type: string}
     * @throws LocalizedException
     */
    public function getProtocol(array $shipments): array
    {
        [$storeId, $numbers] = $this->numbers($shipments);
        try {
            $document = $this->clientPool->orders($storeId)->getProtocol(new GetDocumentMessage($numbers));
        } catch (PolkurierException $e) {
            throw new LocalizedException(\__('%1', $this->errorMessages->describe($e)), $e);
        }

        return ['file_name' => $this->fileName('protocol', $numbers, 'pdf'), 'content' => $document->getDecodedFile(), 'content_type' => 'application/pdf'];
    }

    /**
     * @param ShipmentInterface[] $shipments
     * @return array{0: int|null, 1: string[]}
     * @throws LocalizedException
     */
    private function numbers(array $shipments): array
    {
        $numbers = [];
        $stores = [];
        foreach ($shipments as $shipment) {
            if ($shipment->getState() === ShipmentInterface::STATE_CREATED && $shipment->getPolkurierNumber() !== null) {
                $numbers[] = $shipment->getPolkurierNumber();
                $stores[$this->accountKey($shipment->getStoreId())] = $shipment->getStoreId();
            }
        }
        if ($numbers === []) {
            throw new LocalizedException(\__('None of the selected shipments is a placed Polkurier order.'));
        }
        if (\count($stores) > 1) {
            throw new LocalizedException(\__('The selected shipments belong to different Polkurier accounts; print them separately.'));
        }

        return [\reset($stores), \array_values(\array_unique($numbers))];
    }

    private function accountKey(?int $storeId): string
    {
        return $this->config->getLogin($storeId) . '|' . (int)$this->config->isSandbox($storeId);
    }

    /**
     * @param string[] $numbers
     */
    private function fileName(string $kind, array $numbers, string $extension): string
    {
        return \sprintf('polkurier-%s-%s.%s', $kind, \count($numbers) === 1 ? \preg_replace('/[^\w-]/', '_', $numbers[0]) : \date('Ymd-His'), $extension);
    }
}
