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
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;
use Polkurier\Webservice\Enum\Order\LabelFileFormatEnum;
use Polkurier\Webservice\Request\Order\GetDocumentMessage;

/**
 * Labels and handover protocols for Polkurier orders. Several orders of one account come back as
 * one document.
 *
 * When there are several, each is read from Polkurier first; those Polkurier no longer has as
 * placed are left out and named. This is because Polkurier silently leaves labels it can't print
 * out of the file, and a protocol with one number it doesn't know fails as a whole.
 */
readonly class DocumentService
{
    public function __construct(
        private ShipmentGateway $shipmentGateway,
        private DateTime $dateTime,
        private StatusRefreshService $statusRefreshService,
    ) {}

    /**
     * @param ShipmentInterface[] $shipments
     * @param LabelFileFormatEnum|null $format a label printer's language, or null for PDF
     *
     * @return array{file_name: string, content: string, content_type: string, left_out: string[]}
     * @throws LocalizedException
     */
    public function getLabels(array $shipments, ?LabelFileFormatEnum $format = null): array
    {
        [$numbers, $leftOut] = $this->numbers($shipments);

        $content = $this->labelContent($numbers, $format);
        [$extension, $contentType] = self::fileType($content);

        return [
            'file_name' => $this->fileName('labels', $numbers, $extension),
            'content' => $content,
            'content_type' => $contentType,
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
        [$numbers, $leftOut] = $this->numbers($shipments);
        $document = $this->shipmentGateway->getProtocol(new GetDocumentMessage($numbers));

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
     * @return array{0: string[], 1: string[]} the order numbers, and why any were left out
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

        return [\array_map('strval', \array_keys($placed)), $leftOut];
    }

    /**
     * Reads each order from Polkurier, as a status refresh does (which also clears those cancelled
     * there), and drops those Polkurier no longer has as placed.
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

    /**
     * The labels as one file. Polkurier merges several orders' labels into one PDF and applies a
     * printer language to one order at a time, so for a printer language each order is asked for
     * alone and the answers are joined. A carrier without that language answers in its own format
     * (a PDF, or a GIF for UPS); when any order does, all of them are asked for again as one PDF.
     *
     * @param string[] $numbers
     */
    private function labelContent(array $numbers, ?LabelFileFormatEnum $format): string
    {
        if ($format === null || \count($numbers) === 1) {
            return $this->shipmentGateway->getLabel(new GetDocumentMessage($numbers, $format))->getDecodedFile();
        }
        $parts = [];
        foreach ($numbers as $number) {
            $part = $this->shipmentGateway->getLabel(new GetDocumentMessage([$number], $format))->getDecodedFile();
            if (self::fileType($part)[0] !== 'txt') {
                return $this->shipmentGateway->getLabel(new GetDocumentMessage($numbers))->getDecodedFile();
            }
            $parts[] = \rtrim($part, "\r\n");
        }

        return \implode("\n", $parts) . "\n";
    }

    /**
     * The file's extension and content type, read from its first bytes: Polkurier's answer doesn't
     * say which format it is in.
     *
     * @return array{string, string}
     */
    private static function fileType(string $content): array
    {
        return match (true) {
            \str_starts_with($content, '%PDF') => ['pdf', 'application/pdf'],
            \str_starts_with($content, 'GIF8') => ['gif', 'image/gif'],
            \str_starts_with($content, "\x89PNG") => ['png', 'image/png'],
            default => ['txt', 'text/plain'],
        };
    }
}
