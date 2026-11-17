<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Listing\Shipment;

use Magento\Framework\AuthorizationInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponent\Processor;
use Magento\Framework\View\Element\UiComponentFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Screen\RetriableShipmentFinder;
use Polkurier\Shipping\Model\ShipmentFactory;
use Polkurier\Shipping\Ui\Component\Listing\Shipment\ActionsColumn;

#[CoversClass(ActionsColumn::class)]
#[AllowMockObjectsWithoutExpectations]
class ActionsColumnTest extends TestCase
{
    /**
     * @var string[] the ACL resources allowed
     */
    private array $allowed = ['Magento_Sales::ship', 'Polkurier_Shipping::shipment_cancel'];

    public function testAPlacedOrderPrintsFollowsAndCancels(): void
    {
        $actions = $this->buildActions(ShipmentInterface::STATE_CREATED, shipmentId: 11);

        self::assertSame(['view', 'order', 'label', 'protocol', 'refresh', 'cancel'], \array_keys($actions));
        self::assertTrue($actions['refresh']['post']);
        self::assertSame('polkurier/shipment/cancel id=5 order_id=29', $actions['cancel']['href']);
        self::assertArrayHasKey('confirm', $actions['cancel']);
    }

    public function testAParcelTheCarrierHasIsntOfferedForCancelling(): void
    {
        self::assertArrayNotHasKey('cancel', $this->buildActions(ShipmentInterface::STATE_CREATED, statusCode: 'WP'));
    }

    public function testAPlacedOrderWithoutItsMagentoShipmentCanCreateIt(): void
    {
        self::assertArrayHasKey('ship', $this->buildActions(ShipmentInterface::STATE_CREATED));
    }

    public function testWithoutThePermissionsOnlyFollowingIsOffered(): void
    {
        $this->allowed = [];

        self::assertSame(
            ['view', 'order', 'label', 'protocol', 'refresh'],
            \array_keys($this->buildActions(ShipmentInterface::STATE_CREATED)),
        );
        self::assertSame(['view', 'order'], \array_keys($this->buildActions(ShipmentInterface::STATE_FAILED)));
        self::assertSame(['view', 'order'], \array_keys($this->buildActions(ShipmentInterface::STATE_UNKNOWN)));
    }

    public function testOneNotPlacedIsSentAgainOrDiscarded(): void
    {
        $actions = $this->buildActions(ShipmentInterface::STATE_FAILED);

        self::assertSame(['view', 'order', 'retry', 'discard'], \array_keys($actions));
        self::assertSame('polkurier/shipment/create order_id=29 retry=5', $actions['retry']['href']);
    }

    public function testOneNotPlacedThatCantBeSentAgainCanStillBeDiscarded(): void
    {
        self::assertSame(
            ['view', 'order', 'discard'],
            \array_keys($this->buildActions(ShipmentInterface::STATE_FAILED, canSendAgain: false)),
        );
    }

    public function testOneWithoutAnAnswerIsOnlyLookedAt(): void
    {
        self::assertSame(['view', 'order'], \array_keys($this->buildActions(ShipmentInterface::STATE_UNKNOWN)));
        self::assertSame(['view', 'order'], \array_keys($this->buildActions(ShipmentInterface::STATE_PENDING)));
    }

    public function testACancelledOneIsOnlyLookedAt(): void
    {
        self::assertSame(['view', 'order'], \array_keys($this->buildActions(ShipmentInterface::STATE_CANCELLED)));
    }

    public function testOnTheOrdersPageItsOrderIsntLinked(): void
    {
        self::assertSame(
            ['view'],
            \array_keys($this->buildActions(ShipmentInterface::STATE_UNKNOWN, onOrderPage: true)),
        );
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function buildActions(
        string $state,
        ?int $shipmentId = null,
        bool $canSendAgain = true,
        bool $onOrderPage = false,
        ?string $statusCode = null,
    ): array {
        $context = $this->createStub(ContextInterface::class);
        $context->method('getProcessor')->willReturn($this->createStub(Processor::class));
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static function (string $path, array $params = []): string {
            $query = [];
            foreach ($params as $name => $value) {
                $query[] = $name . '=' . $value;
            }

            return \trim($path . ' ' . \implode(' ', $query));
        });
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')
            ->willReturnCallback(fn(string $resource): bool => \in_array($resource, $this->allowed, true));
        $sendAgain = $this->createStub(RetriableShipmentFinder::class);
        $sendAgain->method('isPossibleFor')->willReturn($canSendAgain);
        $column = new ActionsColumn(
            $context,
            $this->createStub(UiComponentFactory::class),
            $url,
            $authorization,
            $sendAgain,
            $this->createShipmentFactory(),
            [],
            [
                'name' => 'actions',
                'config' => ['onOrderPage' => $onOrderPage],
            ],
        );

        $source = $column->prepareDataSource(['data' => ['items' => [[
            'entity_id' => '5',
            'order_id' => '29',
            'state' => $state,
            'shipment_id' => $shipmentId,
            'polkurier_number' => '24680-27',
            'carrier_code' => 'DPD',
            'status_code' => $statusCode,
        ]]]]);

        return $source['data']['items'][0]['actions'];
    }

    private function createShipmentFactory(): ShipmentFactory
    {
        $factory = $this->createStub(ShipmentFactory::class);
        $factory->method('create')->willReturnCallback(function (array $arguments): Shipment {
            /** @var Shipment $shipment */
            $shipment = (new ObjectManager($this))->getObject(Shipment::class);

            return $shipment->setData($arguments['data']);
        });

        return $factory;
    }
}
