<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Label;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\RequestedShipmentResolver;
use Polkurier\Shipping\Model\Shipment\DocumentService;
use Polkurier\Shipping\Test\Unit\Support\AdminActionTestCase;
use Polkurier\Webservice\Enum\Order\LabelFileFormatEnum;

#[CoversClass(Label::class)]
#[AllowMockObjectsWithoutExpectations]
class LabelActionTest extends AdminActionTestCase
{
    /**
     * @var array{0: string, 1: array<string, mixed>, 2: string, 3: string}|null the file answered with:
     *     its name, content, folder and content type
     */
    private ?array $file = null;

    /**
     * @return iterable<string, array{mixed, LabelFileFormatEnum|null}>
     */
    public static function provideFormats(): iterable
    {
        yield 'a printer language' => ['ZPL', LabelFileFormatEnum::Zpl];
        yield 'none' => [null, null];
        yield 'an unknown one' => ['XYZ', null];
        yield 'a list' => [['ZPL'], null];
    }

    #[DataProvider('provideFormats')]
    public function testTheLabelsComeInTheFormatAskedForOrAsAPdf(mixed $format, ?LabelFileFormatEnum $expected): void
    {
        $this->params = ['ids' => '4,5', 'format' => $format];
        $documents = $this->createMock(DocumentService::class);
        $documents->expects(self::once())->method('getLabels')
            ->with([], $expected)
            ->willReturn($this->buildDocument([]));

        $this->createLabel($documents)->execute();

        self::assertSame('polkurier-labels.txt', $this->file[0] ?? null);
    }

    public function testTheOrdersLeftOutAreNamedWithTheFile(): void
    {
        $documents = $this->createStub(DocumentService::class);
        $documents->method('getLabels')->willReturn($this->buildDocument(['Polkurier order 18015-2866 was left out.']));

        $this->createLabel($documents)->execute();

        self::assertSame(['Polkurier order 18015-2866 was left out.'], $this->messages['warning']);
        self::assertSame(
            ['polkurier-labels.txt', ['type' => 'string', 'value' => '^XA^XZ', 'rm' => false], 'var', 'text/plain'],
            $this->file,
        );
    }

    /**
     * @param string[] $leftOut
     *
     * @return array{file_name: string, content: string, content_type: string, left_out: string[]}
     */
    private function buildDocument(array $leftOut): array
    {
        return [
            'file_name' => 'polkurier-labels.txt',
            'content' => '^XA^XZ',
            'content_type' => 'text/plain',
            'left_out' => $leftOut,
        ];
    }

    private function createLabel(DocumentService $documents): Label
    {
        $requested = $this->createStub(RequestedShipmentResolver::class);
        $requested->method('getSelected')->willReturn([]);
        $files = $this->createStub(FileFactory::class);
        $files->method('create')->willReturnCallback(
            function (string $name, array $content, string $folder, string $type): ResponseInterface {
                $this->file = [$name, $content, $folder, $type];

                return $this->createStub(ResponseInterface::class);
            },
        );

        return new Label($this->createContext(), $requested, $documents, $files);
    }
}
