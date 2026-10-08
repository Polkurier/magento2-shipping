<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Sdk\ShipmentGateway;
use Polkurier\Shipping\Model\Shipment\DocumentService;
use Polkurier\Shipping\Model\Shipment\Status\StatusRefreshService;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Webservice\Enum\Order\LabelFileFormatEnum;
use Polkurier\Webservice\Request\Order\GetDocumentMessage;
use Polkurier\Webservice\Response\Order\DocumentMessage;

#[CoversClass(DocumentService::class)]
#[AllowMockObjectsWithoutExpectations]
class DocumentServiceTest extends TestCase
{
    private ?GetDocumentMessage $asked = null;

    /**
     * @var GetDocumentMessage[] every request, in order
     */
    private array $askedAll = [];

    /**
     * @var array<int, string|null> the state each shipment, by id, is in at Polkurier: null for
     *     one Polkurier doesn't know
     */
    private array $remoteStates = [];

    /**
     * @var int[] the shipments read from Polkurier
     */
    private array $refreshed = [];

    public function testLabelsAreOnePdfOfThePlacedOrdersEachOnce(): void
    {
        $file = $this->documents()->getLabels([
            $this->shipment('18015-2865', 1),
            $this->shipment('18015-2866', 2),
            $this->shipment('18015-2865', 1),
            $this->shipment(null, 1, ShipmentInterface::STATE_UNKNOWN),
            $this->shipment('18015-2867', 1, ShipmentInterface::STATE_CANCELLED),
        ]);

        self::assertSame(['18015-2865', '18015-2866'], $this->asked?->getOrderNo());
        self::assertNull($this->asked->getFileFormat());
        self::assertSame([
            'file_name' => 'polkurier-labels-20260930-100000.pdf',
            'content' => '%PDF-1.4',
            'content_type' => 'application/pdf',
            'left_out' => [],
        ], $file);
    }

    public function testSeveralAreReadFromPolkurierAndThoseNoLongerPlacedLeftOut(): void
    {
        $this->remoteStates = [
            1 => ShipmentInterface::STATE_CREATED,
            2 => ShipmentInterface::STATE_CANCELLED,
            3 => null,
        ];

        $file = $this->documents()->getProtocol([
            $this->shipment('711400-24', 1, id: 1),
            $this->shipment('711400-25', 1, id: 2),
            $this->shipment('711400-99', 1, id: 3),
        ]);

        self::assertSame([1, 2, 3], $this->refreshed);
        self::assertSame(
            ['711400-24'],
            $this->asked?->getOrderNo(),
            'Polkurier fails a whole protocol over one number it doesn\'t know.',
        );
        self::assertSame([
            'Polkurier order 711400-25 was left out: it was cancelled in Polkurier.',
            'Polkurier order 711400-99 was left out: Polkurier has no order number 711400-99 on this account.',
        ], $file['left_out']);
    }

    public function testOneIsAskedForStraightAway(): void
    {
        $this->documents()->getLabels([$this->shipment('711400-24', 1, id: 1)]);

        self::assertSame([], $this->refreshed, 'Polkurier says itself when the only one asked for can\'t be printed.');
    }

    public function testNothingIsAskedForWhenNoneIsPlacedAnyMore(): void
    {
        $this->remoteStates = [1 => ShipmentInterface::STATE_CANCELLED, 2 => ShipmentInterface::STATE_CANCELLED];
        $this->expectExceptionMessage(
            'None of the selected shipments is placed in Polkurier any more. Polkurier order 711400-24 was left out',
        );

        try {
            $this->documents()->getLabels([
                $this->shipment('711400-24', 1, id: 1),
                $this->shipment('711400-25', 1, id: 2),
            ]);
        } finally {
            self::assertNull($this->asked);
        }
    }

    public function testAPrinterFormatMakesATextFileAndNoneAPdf(): void
    {
        $documents = $this->documents();

        $zpl = $documents->getLabels([$this->shipment('18015-2865', 1)], LabelFileFormatEnum::Zpl);
        self::assertSame(LabelFileFormatEnum::Zpl, $this->asked?->getFileFormat());
        self::assertSame(['polkurier-labels-18015-2865.txt', 'text/plain'], [$zpl['file_name'], $zpl['content_type']]);

        $pdf = $documents->getLabels([$this->shipment('18015-2865', 1)]);
        self::assertNull($this->asked->getFileFormat());
        self::assertSame('application/pdf', $pdf['content_type']);
    }

    public function testAPdfAnswerToAPrinterFormatIsServedAsAPdf(): void
    {
        $zpl = $this->documents(['18015-2865'])
            ->getLabels([$this->shipment('18015-2865', 1)], LabelFileFormatEnum::Zpl);

        self::assertSame(['polkurier-labels-18015-2865.pdf', 'application/pdf'], [$zpl['file_name'], $zpl['content_type']]);
    }

    public function testSeveralOrdersInAPrinterLanguageAreAskedForOneByOneAndJoined(): void
    {
        $zpl = $this->documents()->getLabels(
            [$this->shipment('18015-2865', 1, id: 1), $this->shipment('18015-2866', 1, id: 2)],
            LabelFileFormatEnum::Zpl,
        );

        self::assertSame(
            [['18015-2865'], ['18015-2866']],
            \array_map(fn(GetDocumentMessage $m): array => $m->getOrderNo(), $this->askedAll),
        );
        self::assertSame("^XA^FD18015-2865^FS^XZ\n^XA^FD18015-2866^FS^XZ\n", $zpl['content']);
        self::assertSame('text/plain', $zpl['content_type']);
    }

    public function testSeveralOrdersBecomeOnePdfWhenACarrierHasNoSuchPrinterLanguage(): void
    {
        $file = $this->documents(['18015-2866'])->getLabels(
            [$this->shipment('18015-2865', 1, id: 1), $this->shipment('18015-2866', 1, id: 2)],
            LabelFileFormatEnum::Epl,
        );

        self::assertSame(['18015-2865', '18015-2866'], $this->asked?->getOrderNo());
        self::assertNull($this->asked->getFileFormat());
        self::assertSame(['%PDF-1.4', 'application/pdf'], [$file['content'], $file['content_type']]);
    }

    public function testTheProtocolIsNamedAfterItsOnlyOrder(): void
    {
        $file = $this->documents()->getProtocol([$this->shipment('18015/2865', 1)]);

        self::assertSame('polkurier-protocol-18015_2865.pdf', $file['file_name']);
    }

    public function testNothingIsAskedForWithoutAPlacedOrder(): void
    {
        $this->expectExceptionMessage('None of the selected shipments is a placed Polkurier order.');

        try {
            $this->documents()->getLabels([$this->shipment(null, 1, ShipmentInterface::STATE_FAILED)]);
        } finally {
            self::assertNull($this->asked);
        }
    }

    /**
     * @param string[] $pdfFor orders whose carrier answers a printer language with a PDF
     */
    private function documents(array $pdfFor = []): DocumentService
    {
        $gateway = $this->createStub(ShipmentGateway::class);
        $answer = function (GetDocumentMessage $message) use ($pdfFor): DocumentMessage {
            $this->asked = $message;
            $this->askedAll[] = $message;
            $orders = $message->getOrderNo();
            $printer = $message->getFileFormat() !== null && \count($orders) === 1
                && !\in_array($orders[0], $pdfFor, true);

            return new DocumentMessage(\base64_encode($printer ? '^XA^FD' . $orders[0] . '^FS^XZ' : '%PDF-1.4'));
        };
        $gateway->method('getLabel')->willReturnCallback($answer);
        $gateway->method('getProtocol')->willReturnCallback($answer);

        $refresh = $this->createStub(StatusRefreshService::class);
        $refresh->method('refresh')->willReturnCallback(function (int $id): ShipmentInterface {
            $this->refreshed[] = $id;
            $state = \array_key_exists($id, $this->remoteStates)
                ? $this->remoteStates[$id]
                : ShipmentInterface::STATE_CREATED;
            if ($state === null) {
                throw new LocalizedException(__('Polkurier has no order number %1 on this account.', '711400-99'));
            }

            return $this->shipment('711400-' . $id, 1, $state, $id);
        });

        return new DocumentService(
            $gateway,
            new FixedDateTime((int)\strtotime('2026-09-30 10:00:00 UTC')),
            $refresh,
        );
    }

    private function shipment(
        ?string $number,
        int $storeId,
        string $state = ShipmentInterface::STATE_CREATED,
        int $id = 0,
    ): ShipmentInterface {
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getId')->willReturn($id);
        $shipment->method('getPolkurierNumber')->willReturn($number);
        $shipment->method('getStoreId')->willReturn($storeId);
        $shipment->method('getState')->willReturn($state);

        return $shipment;
    }
}
