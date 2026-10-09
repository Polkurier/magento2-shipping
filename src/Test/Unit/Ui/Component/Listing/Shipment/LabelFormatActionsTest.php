<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Listing\Shipment;

use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config\Source\LabelFormat;
use Polkurier\Shipping\Ui\Component\Listing\Shipment\LabelFormatActions;

#[CoversClass(LabelFormatActions::class)]
#[AllowMockObjectsWithoutExpectations]
class LabelFormatActionsTest extends TestCase
{
    public function testEachFormatPrintsWithItsOwnUrlPdfFirst(): void
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $path, array $params = []): string => $path . \http_build_query($params, '', '/'),
        );

        $actions = (new LabelFormatActions($url, new LabelFormat()))->jsonSerialize();

        self::assertSame(['type' => 'label_pdf', 'label' => 'PDF', 'url' => 'polkurier/shipment/label'], $actions[0]);
        self::assertSame('polkurier/shipment/labelformat=ZPL300', $actions[2]['url']);
        self::assertSame(['PDF', 'ZPL (203 dpi)', 'ZPL (300 dpi)', 'EPL', 'Datamax'], \array_column($actions, 'label'));
    }
}
