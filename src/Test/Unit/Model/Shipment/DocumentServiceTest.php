<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
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
     * @var array<int, int[]> the shipments read from Polkurier, by call
     */
    private array $refreshed = [];

    /**
     * @var array<string, string|LocalizedException> what Polkurier answers when asked for one order
     *     alone, by its number, if not the usual
     */
    private array $labels = [];

    public function testLabelsAreOnePdfOfThePlacedOrdersEachOnce(): void
    {
        $file = $this->documents()->getLabels([
            $this->shipment('18015-2865', 1, id: 1),
            $this->shipment('18015-2866', 2, id: 2),
            $this->shipment('18015-2865', 1, id: 1),
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

        self::assertSame([[1, 2, 3]], $this->refreshed, 'Read together.');
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
        self::assertSame("^XA^FD18015-2865^FS^XZ\n^XA^FD18015-2866^FS^XZ", $zpl['content']);
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

    public function testPrinterLabelsAreJoinedAsReceived(): void
    {
        $this->labels = [
            '18015-2865' => "L\rD11\rE\r",
            '18015-2866' => "L\rD11\rE",
            '18015-2867' => "^XA^XZ\r\n",
        ];

        $file = $this->documents()->getLabels(
            [
                $this->shipment('18015-2865', 1, id: 1),
                $this->shipment('18015-2866', 1, id: 2),
                $this->shipment('18015-2867', 1, id: 3),
            ],
            LabelFileFormatEnum::Datamax,
        );

        self::assertSame("L\rD11\rE\rL\rD11\rE\n^XA^XZ\r\n", $file['content'], 'Datamax lines end in a CR alone.');
    }

    public function testAnOrderWithoutAPrinterLabelIsLeftOutAndTheRestJoined(): void
    {
        $this->remoteStates = [4 => ShipmentInterface::STATE_CANCELLED];
        $this->labels = [
            '18015-2866' => new LocalizedException(__('The label isn\'t ready yet.')),
            '18015-2867' => '',
        ];

        $file = $this->documents()->getLabels(
            [
                $this->shipment('18015-2865', 1, id: 1),
                $this->shipment('18015-2866', 1, id: 2),
                $this->shipment('18015-2867', 1, id: 3),
                $this->shipment('18015-2868', 1, id: 4),
            ],
            LabelFileFormatEnum::Zpl,
        );

        self::assertSame([
            'file_name' => 'polkurier-labels-18015-2865.txt',
            'content' => '^XA^FD18015-2865^FS^XZ',
            'content_type' => 'text/plain',
            'left_out' => [
                'Polkurier order 18015-2868 was left out: it was cancelled in Polkurier.',
                'Polkurier order 18015-2866 was left out: The label isn\'t ready yet.',
                'Polkurier order 18015-2867 was left out: Polkurier returned no label for 18015-2867.',
            ],
        ], $file);
    }

    public function testThePdfInsteadOfPrinterLabelsLeavesOutTheOrdersThatFailed(): void
    {
        $this->labels = [
            '18015-2865' => new LocalizedException(__('The label isn\'t ready yet.')),
            '18015-2866' => '%PDF-1.4',
        ];

        $file = $this->documents()->getLabels(
            [
                $this->shipment('18015-2865', 1, id: 1),
                $this->shipment('18015-2866', 1, id: 2),
                $this->shipment('18015-2867', 1, id: 3),
            ],
            LabelFileFormatEnum::Zpl,
        );

        self::assertSame(['18015-2866', '18015-2867'], $this->asked?->getOrderNo());
        self::assertNull($this->asked->getFileFormat());
        self::assertSame('application/pdf', $file['content_type']);
        self::assertSame(['Polkurier order 18015-2865 was left out: The label isn\'t ready yet.'], $file['left_out']);
    }

    public function testWhenNoOrderHasAPrinterLabelTheFirstErrorIsThrown(): void
    {
        $this->labels = [
            '18015-2865' => new LocalizedException(__('The label isn\'t ready yet.')),
            '18015-2866' => new LocalizedException(__('The order was cancelled.')),
        ];
        $this->expectExceptionMessage('The label isn\'t ready yet.');

        $this->documents()->getLabels(
            [$this->shipment('18015-2865', 1, id: 1), $this->shipment('18015-2866', 1, id: 2)],
            LabelFileFormatEnum::Zpl,
        );
    }

    public function testAnEmptyAnswerIsNoLabel(): void
    {
        $this->labels = ['18015-2865' => ''];
        $this->expectExceptionMessage('Polkurier returned no label for 18015-2865.');

        $this->documents()->getLabels([$this->shipment('18015-2865', 1)]);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function fileTypes(): iterable
    {
        yield 'PDF' => ['%PDF-1.4', 'pdf', 'application/pdf'];
        yield 'PDF after a byte order mark and whitespace' => ["\xEF\xBB\xBF \r\n%PDF-1.4", 'pdf', 'application/pdf'];
        yield 'GIF' => ['GIF89a', 'gif', 'image/gif'];
        yield 'PNG' => ["\x89PNG\r\n", 'png', 'image/png'];
        yield 'JPEG' => ["\xFF\xD8\xFF\xE0", 'jpg', 'image/jpeg'];
        yield 'printer language' => ['^XA^XZ', 'txt', 'text/plain'];
    }

    #[DataProvider('fileTypes')]
    public function testTheFileTypeIsReadFromTheContent(string $content, string $extension, string $contentType): void
    {
        $this->labels = ['18015-2865' => $content];

        $file = $this->documents()->getLabels([$this->shipment('18015-2865', 1)]);

        self::assertSame(
            ['polkurier-labels-18015-2865.' . $extension, $contentType, $content],
            [$file['file_name'], $file['content_type'], $file['content']],
        );
    }

    public function testUpToFiftyOrdersArePrintedInAPrinterLanguage(): void
    {
        $file = $this->documents()->getLabels($this->shipments(50), LabelFileFormatEnum::Zpl);

        self::assertCount(50, $this->askedAll);
        self::assertSame([], $file['left_out']);
    }

    public function testMoreOrdersInAPrinterLanguageAreRefusedBeforeAskingPolkurier(): void
    {
        $this->expectExceptionMessage(
            'Labels in ZPL can be printed for up to 50 orders at a time. Select fewer orders, or print them as PDF.',
        );

        try {
            $this->documents()->getLabels($this->shipments(51), LabelFileFormatEnum::Zpl);
        } finally {
            self::assertSame([], $this->askedAll);
            self::assertSame([], $this->refreshed);
        }
    }

    public function testAPdfOfMoreOrdersIsOneRequest(): void
    {
        $file = $this->documents()->getLabels($this->shipments(51));

        self::assertCount(1, $this->askedAll);
        self::assertCount(51, $this->asked?->getOrderNo() ?? []);
        self::assertSame('application/pdf', $file['content_type']);
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
            $label = \count($orders) === 1 ? $this->labels[$orders[0]] ?? null : null;
            if ($label instanceof LocalizedException) {
                throw $label;
            }
            if ($label !== null) {
                return new DocumentMessage(\base64_encode($label));
            }
            $printer = $message->getFileFormat() !== null && \count($orders) === 1
                && !\in_array($orders[0], $pdfFor, true);

            return new DocumentMessage(\base64_encode($printer ? '^XA^FD' . $orders[0] . '^FS^XZ' : '%PDF-1.4'));
        };
        $gateway->method('getLabel')->willReturnCallback($answer);
        $gateway->method('getProtocol')->willReturnCallback($answer);

        $refresh = $this->createStub(StatusRefreshService::class);
        $refresh->method('refreshMany')->willReturnCallback(function (array $ids): array {
            $this->refreshed[] = $ids;
            $results = [];
            foreach ($ids as $id) {
                $state = \array_key_exists($id, $this->remoteStates)
                    ? $this->remoteStates[$id]
                    : ShipmentInterface::STATE_CREATED;
                $results[$id] = $state === null
                    ? new LocalizedException(__('Polkurier has no order number %1 on this account.', '711400-99'))
                    : $this->shipment('711400-' . $id, 1, $state, $id);
            }

            return $results;
        });

        return new DocumentService(
            $gateway,
            new FixedDateTime((int)\strtotime('2026-09-30 10:00:00 UTC')),
            $refresh,
        );
    }

    /**
     * @return ShipmentInterface[] that many placed shipments, each with its own order
     */
    private function shipments(int $count): array
    {
        return \array_map(
            fn(int $id): ShipmentInterface => $this->shipment('18015-' . (2800 + $id), 1, id: $id),
            \range(1, $count),
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
