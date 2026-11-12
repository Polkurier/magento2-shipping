<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
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
 * When there are several, they are read from Polkurier first; those Polkurier no longer has as
 * placed are left out and named. This is because Polkurier silently leaves labels it can't print
 * out of the file, and a protocol with one number it doesn't know fails as a whole.
 */
readonly class DocumentService
{
    /**
     * The most orders whose labels are fetched in a printer language at once: Polkurier is asked for
     * each order alone.
     */
    public const int MAX_PRINTER_LANGUAGE_ORDERS = 50;

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
        [$numbers, $leftOut] = $this->collectNumbers($shipments, $format);

        if ($format === null || \count($numbers) === 1) {
            $content = $this->fetchLabel($numbers, $format);
        } else {
            [$content, $numbers, $failed] = $this->fetchPrinterLabels($numbers, $format);
            $leftOut = [...$leftOut, ...$failed];
        }
        [$extension, $contentType] = self::detectFileType($content);

        return [
            'file_name' => $this->buildFileName('labels', $numbers, $extension),
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
        [$numbers, $leftOut] = $this->collectNumbers($shipments);
        $document = $this->shipmentGateway->getProtocol(new GetDocumentMessage($numbers));

        return [
            'file_name' => $this->buildFileName('protocol', $numbers, 'pdf'),
            'content' => $document->getDecodedFile(),
            'content_type' => 'application/pdf',
            'left_out' => $leftOut,
        ];
    }

    /**
     * @param ShipmentInterface[] $shipments
     * @param LabelFileFormatEnum|null $format the printer language labels are asked for in, which
     *     limits their number to {@see self::MAX_PRINTER_LANGUAGE_ORDERS}
     *
     * @return array{0: string[], 1: string[]} the order numbers, and why any were left out
     * @throws LocalizedException
     */
    private function collectNumbers(array $shipments, ?LabelFileFormatEnum $format = null): array
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
        if ($format !== null && \count($placed) > self::MAX_PRINTER_LANGUAGE_ORDERS) {
            throw new LocalizedException(__(
                'Labels in %1 can be printed for up to %2 orders at a time. '
                . 'Select fewer orders, or print them as PDF.',
                $format->value,
                self::MAX_PRINTER_LANGUAGE_ORDERS,
            ));
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
     * Reads the orders from Polkurier, as a status refresh does (which also clears those cancelled
     * there), and drops those Polkurier no longer has as placed.
     *
     * @param array<string, ShipmentInterface> $placed by Polkurier number
     *
     * @param-out array<string, ShipmentInterface> $placed
     * @return string[] why each was left out
     */
    private function leaveOutUnplaced(array &$placed): array
    {
        $refreshed = $this->statusRefreshService->refreshMany(\array_values(\array_map(
            static fn(ShipmentInterface $shipment): int => (int)$shipment->getId(),
            $placed,
        )));
        $leftOut = [];
        foreach ($placed as $number => $shipment) {
            $result = $refreshed[(int)$shipment->getId()];
            if ($result instanceof LocalizedException) {
                $leftOut[] = (string)__('Polkurier order %1 was left out: %2', $number, $result->getMessage());
                unset($placed[$number]);
                continue;
            }
            if ($result instanceof \Throwable) {
                throw $result;
            }
            if ($result->getState() !== ShipmentInterface::STATE_CREATED) {
                $leftOut[] = (string)__('Polkurier order %1 was left out: it was cancelled in Polkurier.', $number);
                unset($placed[$number]);
            }
        }

        return $leftOut;
    }

    /**
     * @param string[] $numbers
     */
    private function buildFileName(string $kind, array $numbers, string $extension): string
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
     * @param string[] $numbers
     *
     * @throws LocalizedException
     */
    private function fetchLabel(array $numbers, ?LabelFileFormatEnum $format): string
    {
        $content = $this->shipmentGateway->getLabel(new GetDocumentMessage($numbers, $format))->getDecodedFile();
        if ($content === '') {
            throw new LocalizedException(__('Polkurier returned no label for %1.', \implode(', ', $numbers)));
        }

        return $content;
    }

    /**
     * The labels of several orders in a printer language, as one file. Polkurier merges several
     * orders' labels into one PDF and applies a printer language to one order at a time, so each
     * order is asked for alone and the answers are joined as received. An order Polkurier fails or
     * answers with nothing is left out. A carrier without that language answers in its own format
     * (a PDF, or a GIF for UPS); when any order does, the orders not left out are asked for again
     * as one PDF.
     *
     * @param string[] $numbers
     *
     * @return array{string, string[], string[]} the file, the orders in it, and why any were left out
     * @throws LocalizedException the first order's error, when every order fails
     */
    private function fetchPrinterLabels(array $numbers, LabelFileFormatEnum $format): array
    {
        $content = '';
        $joined = [];
        $failed = [];
        $leftOut = [];
        $firstError = null;
        foreach ($numbers as $number) {
            try {
                $part = $this->fetchLabel([$number], $format);
            } catch (LocalizedException $e) {
                $firstError ??= $e;
                $failed[] = $number;
                $leftOut[] = (string)__('Polkurier order %1 was left out: %2', $number, $e->getMessage());
                continue;
            }
            if (self::detectFileType($part)[0] !== 'txt') {
                $kept = \array_values(\array_diff($numbers, $failed));

                return [$this->fetchLabel($kept, null), $kept, $leftOut];
            }
            // Each part as received (Datamax lines end in a CR alone), with a line break between
            // parts only where the one before doesn't end in one.
            if ($content !== '' && !\in_array(\substr($content, -1), ["\r", "\n"], true)) {
                $content .= "\n";
            }
            $content .= $part;
            $joined[] = $number;
        }
        if ($joined === [] && $firstError !== null) {
            throw $firstError;
        }

        return [$content, $joined, $leftOut];
    }

    /**
     * The file's extension and content type, read from its first bytes: Polkurier's answer doesn't
     * say which format it is in.
     *
     * @return array{string, string}
     */
    private static function detectFileType(string $content): array
    {
        // A UTF-8 byte order mark and whitespace before the format's signature don't count.
        $head = \substr($content, 0, 1024);
        $head = \ltrim(\str_starts_with($head, "\xEF\xBB\xBF") ? \substr($head, 3) : $head);

        return match (true) {
            \str_starts_with($head, '%PDF') => ['pdf', 'application/pdf'],
            \str_starts_with($head, 'GIF8') => ['gif', 'image/gif'],
            \str_starts_with($head, "\x89PNG") => ['png', 'image/png'],
            \str_starts_with($head, "\xFF\xD8\xFF") => ['jpg', 'image/jpeg'],
            default => ['txt', 'text/plain'],
        };
    }
}
