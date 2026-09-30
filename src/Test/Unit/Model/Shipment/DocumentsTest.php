<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Sdk\ShipmentGatewayInterface;
use Polkurier\Shipping\Model\Shipment\Documents;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;
use Polkurier\Webservice\Enum\Order\LabelFileFormatEnum;
use Polkurier\Webservice\Request\Order\GetDocumentMessage;
use Polkurier\Webservice\Response\Order\DocumentMessage;

#[CoversClass(Documents::class)]
#[AllowMockObjectsWithoutExpectations]
class DocumentsTest extends TestCase
{
    private ?GetDocumentMessage $asked = null;
    private ?LabelFileFormatEnum $format = null;

    /**
     * @var array<int|null> the stores the label format was read for
     */
    private array $formatStores = [];

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
        self::assertSame([1], $this->formatStores, 'The first shipment\'s store says the label format.');
        self::assertSame(['file_name' => 'polkurier-labels-20260930-100000.pdf', 'content' => '%PDF-1.4', 'content_type' => 'application/pdf'], $file);
    }

    public function testAPrinterFormatMakesATextFileUnlessMagentoWantsAPdf(): void
    {
        $this->format = LabelFileFormatEnum::Zpl;
        $documents = $this->documents();

        $zpl = $documents->getLabels([$this->shipment('18015-2865', 1)]);
        self::assertSame(LabelFileFormatEnum::Zpl, $this->asked?->getFileFormat());
        self::assertSame(['polkurier-labels-18015-2865.txt', 'text/plain'], [$zpl['file_name'], $zpl['content_type']]);

        $pdf = $documents->getLabels([$this->shipment('18015-2865', 1)], true);
        self::assertNull($this->asked->getFileFormat());
        self::assertSame('application/pdf', $pdf['content_type']);
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

    private function documents(): Documents
    {
        $gateway = $this->createStub(ShipmentGatewayInterface::class);
        $answer = function (GetDocumentMessage $message): DocumentMessage {
            $this->asked = $message;

            return new DocumentMessage(\base64_encode('%PDF-1.4'));
        };
        $gateway->method('getLabel')->willReturnCallback($answer);
        $gateway->method('getProtocol')->willReturnCallback($answer);
        $config = $this->createStub(Config::class);
        $config->method('getLabelFormat')->willReturnCallback(function (?int $storeId): ?LabelFileFormatEnum {
            $this->formatStores[] = $storeId;

            return $this->format;
        });

        return new Documents($gateway, $config, new FixedDateTime((int)\strtotime('2026-09-30 10:00:00 UTC')));
    }

    private function shipment(?string $number, int $storeId, string $state = ShipmentInterface::STATE_CREATED): ShipmentInterface
    {
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getPolkurierNumber')->willReturn($number);
        $shipment->method('getStoreId')->willReturn($storeId);
        $shipment->method('getState')->willReturn($state);

        return $shipment;
    }
}
