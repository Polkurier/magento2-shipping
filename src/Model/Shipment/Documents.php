<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Sdk\ShipmentGatewayInterface;
use Polkurier\Webservice\Request\Order\GetDocumentMessage;

/**
 * Labels and handover protocols for Polkurier orders. Several orders of one account come back as
 * one document.
 */
readonly class Documents
{
    public function __construct(
        private ShipmentGatewayInterface $gateway,
        private Config $config,
        private DateTime $dateTime,
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

        $document = $this->gateway->getLabel(new GetDocumentMessage($numbers, $format));

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
        [, $numbers] = $this->numbers($shipments);
        $document = $this->gateway->getProtocol(new GetDocumentMessage($numbers));

        return ['file_name' => $this->fileName('protocol', $numbers, 'pdf'), 'content' => $document->getDecodedFile(), 'content_type' => 'application/pdf'];
    }

    /**
     * @param ShipmentInterface[] $shipments
     * @return array{0: int|null, 1: string[]} the store of the first shipment, whose label format
     *     applies, and the order numbers
     * @throws LocalizedException
     */
    private function numbers(array $shipments): array
    {
        $numbers = [];
        $storeId = null;
        foreach ($shipments as $shipment) {
            if ($shipment->getState() === ShipmentInterface::STATE_CREATED && $shipment->getPolkurierNumber() !== null) {
                $numbers[] = $shipment->getPolkurierNumber();
                $storeId ??= $shipment->getStoreId();
            }
        }
        if ($numbers === []) {
            throw new LocalizedException(\__('None of the selected shipments is a placed Polkurier order.'));
        }

        return [$storeId, \array_values(\array_unique($numbers))];
    }

    /**
     * @param string[] $numbers
     */
    private function fileName(string $kind, array $numbers, string $extension): string
    {
        return \sprintf('polkurier-%s-%s.%s', $kind, \count($numbers) === 1 ? \preg_replace('/[^\w-]/', '_', $numbers[0]) : (string)$this->dateTime->date('Ymd-His'), $extension);
    }
}
