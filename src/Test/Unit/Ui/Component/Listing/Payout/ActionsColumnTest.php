<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Listing\Payout;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\Processor;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Controller\Adminhtml\Payout\Cancel as PayoutCancel;
use Polkurier\Shipping\Ui\Component\Listing\Payout\ActionsColumn;

#[CoversClass(ActionsColumn::class)]
#[AllowMockObjectsWithoutExpectations]
class ActionsColumnTest extends TestCase
{
    public function testAPayoutPolkurierStillLetsGoOfIsOfferedForCancelling(): void
    {
        $rows = $this->buildRows(true);

        self::assertSame(['cancel'], \array_keys($rows[0]['actions'] ?? []));
        self::assertSame('polkurier/payout/cancel W-17', $rows[0]['actions']['cancel']['href']);
        self::assertTrue($rows[0]['actions']['cancel']['post']);
        self::assertArrayNotHasKey('actions', $rows[1], 'One already under way can\'t be cancelled.');
    }

    public function testWithoutThePermissionNoPayoutIsOfferedForCancelling(): void
    {
        $rows = $this->buildRows(false);

        self::assertArrayNotHasKey('actions', $rows[0]);
    }

    /**
     * @return array<int, array<string, mixed>> the rows: a cancellable payout, then one that isn't
     */
    private function buildRows(bool $allowed): array
    {
        $context = $this->createStub(ContextInterface::class);
        $context->method('getProcessor')->willReturn($this->createStub(Processor::class));
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn(string $path, array $params): string => $path . ' ' . $params['number'],
        );
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')->willReturnMap([[PayoutCancel::ADMIN_RESOURCE, null, $allowed]]);
        $column = new ActionsColumn(
            $context,
            $this->createStub(UiComponentFactory::class),
            $url,
            $authorization,
            [],
            ['name' => 'actions'],
        );

        return $column->prepareDataSource(['data' => ['items' => [
            ['number' => 'W-17', 'cancellable' => true],
            ['number' => 'W-16', 'cancellable' => false],
        ]]])['data']['items'];
    }
}
