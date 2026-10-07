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
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;
use Polkurier\Webservice\Request\Order\GetDocumentMessage;

/**
 * Labels and handover protocols for Polkurier orders. Several orders of one account come back as
 * one document.
 *
 * Several are read from Polkurier first, each, and those it no longer has placed are left out and
 * named: Polkurier leaves labels it can't print out of the file without a word, and a protocol
 * with one number it doesn't know fails as a whole.
 */
readonly class DocumentService
{
    public function __construct(
        private ShipmentGateway $gateway,
        private ConfigProvider $configProvider,
        private DateTime $dateTime,
        private StatusRefreshService $statusRefreshService,
    ) {}

    /**
     * @param ShipmentInterface[] $shipments
     * @param bool $pdf a PDF whatever label format is configured, as Magento stores labels as PDF
     *
     * @return array{file_name: string, content: string, content_type: string, left_out: string[]}
     * @throws LocalizedException
     */
    public function getLabels(array $shipments, bool $pdf = false): array
    {
        [$storeId, $numbers, $leftOut] = $this->numbers($shipments);
        $format = $pdf ? null : $this->configProvider->getLabelFormat($storeId);

        $document = $this->gateway->getLabel(new GetDocumentMessage($numbers, $format));

        return $format === null
            ? [
                'file_name' => $this->fileName('labels', $numbers, 'pdf'),
                'content' => $document->getDecodedFile(),
                'content_type' => 'application/pdf',
                'left_out' => $leftOut,
            ]
            : [
                'file_name' => $this->fileName('labels', $numbers, 'txt'),
                'content' => $document->getDecodedFile(),
                'content_type' => 'text/plain',
                'left_out' => $leftOut,
            ];
    }

    /**
     * @param ShipmentInterface[] $shipments
     *
     * @return array{file_name: string, content: string, content_type: string, left_out: string[]}
     * @throws LocalizedException
     */
    public function getProtocol(array $shipments): array
    {
        [, $numbers, $leftOut] = $this->numbers($shipments);
        $document = $this->gateway->getProtocol(new GetDocumentMessage($numbers));

        return [
            'file_name' => $this->fileName('protocol', $numbers, 'pdf'),
            'content' => $document->getDecodedFile(),
            'content_type' => 'application/pdf',
            'left_out' => $leftOut,
        ];
    }

    /**
     * @param ShipmentInterface[] $shipments
     *
     * @return array{0: int|null, 1: string[], 2: string[]} the store of the first shipment, whose
     *     label format applies, the order numbers, and why any were left out
     * @throws LocalizedException
     */
    private function numbers(array $shipments): array
    {
        $placed = [];
        foreach ($shipments as $shipment) {
            $number = $shipment->getPolkurierNumber();
            if ($shipment->getState() === ShipmentInterface::STATE_CREATED && $number !== null) {
                $placed[$number] ??= $shipment;
            }
        }
        if ($placed === []) {
            throw new LocalizedException(__('None of the selected shipments is a placed Polkurier order.'));
        }
        $leftOut = \count($placed) > 1 ? $this->leaveOutUnplaced($placed) : [];
        if ($placed === []) {
            throw new LocalizedException(__(
                'None of the selected shipments is placed in Polkurier any more. %1',
                \implode(' ', $leftOut),
            ));
        }

        return [\reset($placed)->getStoreId(), \array_map('strval', \array_keys($placed)), $leftOut];
    }

    /**
     * Reads each order from Polkurier, as a status refresh does, which also clears those cancelled
     * there, and drops those it no longer has placed.
     *
     * @param array<string, ShipmentInterface> $placed by Polkurier number
     *
     * @param-out array<string, ShipmentInterface> $placed
     * @return string[] why each was left out
     */
    private function leaveOutUnplaced(array &$placed): array
    {
        $leftOut = [];
        foreach ($placed as $number => $shipment) {
            try {
                $state = $this->statusRefreshService->refresh((int)$shipment->getId())->getState();
            } catch (LocalizedException $e) {
                $leftOut[] = (string)__('Polkurier order %1 was left out: %2', $number, $e->getMessage());
                unset($placed[$number]);
                continue;
            }
            if ($state !== ShipmentInterface::STATE_CREATED) {
                $leftOut[] = (string)__('Polkurier order %1 was left out: it was cancelled in Polkurier.', $number);
                unset($placed[$number]);
            }
        }

        return $leftOut;
    }

    /**
     * @param string[] $numbers
     */
    private function fileName(string $kind, array $numbers, string $extension): string
    {
        return \sprintf(
            'polkurier-%s-%s.%s',
            $kind,
            \count($numbers) === 1
                ? \preg_replace('/[^\w-]/', '_', $numbers[0])
                : (string)$this->dateTime->date('Ymd-His'),
            $extension,
        );
    }
}
