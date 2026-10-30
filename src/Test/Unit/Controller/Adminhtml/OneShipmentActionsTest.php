<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\AbstractShipmentAction;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Adopt;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Cancel;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\RequestedShipments;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Ship;
use Polkurier\Shipping\Test\Unit\Support\AdminActionTestCase;

/**
 * The actions on one shipment: linking it to a Polkurier order the merchant names, looking for
 * the one its unanswered creation placed, cancelling it, and creating its Magento shipment.
 */
#[CoversClass(Adopt::class)]
#[CoversClass(Cancel::class)]
#[CoversClass(Ship::class)]
#[AllowMockObjectsWithoutExpectations]
class OneShipmentActionsTest extends AdminActionTestCase
{
    /**
     * @return array<string, array{class-string<AbstractShipmentAction>, string}>
     */
    public static function actions(): array
    {
        return [
            'adopt' => [Adopt::class, 'adopt'],
            'cancel' => [Cancel::class, 'cancel'],
            'ship' => [Ship::class, 'shipOrder'],
        ];
    }

    public function testTheNamedPolkurierOrderIsLinked(): void
    {
        $this->params = ['id' => '41', 'order_id' => '29', 'polkurier_number' => ' 24680-27 '];
        $management = $this->createMock(ShipmentManagementInterface::class);
        $management->expects(self::once())->method('adopt')->with(41, '24680-27')
            ->willReturn($this->shipment(ShipmentInterface::STATE_CREATED, '24680-27'));

        (new Adopt($this->context(), $this->requested(), $management))->execute();

        self::assertSame(['Polkurier order 24680-27 linked.'], $this->messages['success']);
        self::assertSame(['sales/order/view', ['order_id' => 29]], $this->redirect);
    }

    public function testNoNumberLinksNothing(): void
    {
        $this->params = ['id' => '41', 'order_id' => '29', 'polkurier_number' => '  '];
        $management = $this->createMock(ShipmentManagementInterface::class);
        $management->expects(self::never())->method('adopt');

        (new Adopt($this->context(), $this->requested(), $management))->execute();

        self::assertSame([], $this->messages);
        self::assertSame(['sales/order/view', ['order_id' => 29]], $this->redirect);
    }

    public function testACancelledPolkurierOrderIsReported(): void
    {
        $this->params = ['id' => '41', 'order_id' => '29'];
        $management = $this->createMock(ShipmentManagementInterface::class);
        $management->expects(self::once())->method('cancel')->with(41)
            ->willReturn($this->shipment(ShipmentInterface::STATE_CANCELLED, '24680-4'));

        (new Cancel($this->context(), $this->requested(), $management))->execute();

        self::assertSame(['Polkurier order 24680-4 cancelled.'], $this->messages['success']);
        self::assertSame(['sales/order/view', ['order_id' => 29]], $this->redirect);
    }

    public function testTheOrderIsShippedWithItsPolkurierOrder(): void
    {
        $this->params = ['id' => '41', 'order_id' => '29'];
        $management = $this->createMock(ShipmentManagementInterface::class);
        $management->expects(self::once())->method('shipOrder')->with(41)
            ->willReturn($this->shipment(ShipmentInterface::STATE_CREATED, '24680-4'));

        (new Ship($this->context(), $this->requested(), $management))->execute();

        self::assertSame(['The order is shipped with Polkurier order 24680-4.'], $this->messages['success']);
        self::assertSame(['sales/order/view', ['order_id' => 29]], $this->redirect);
    }

    /**
     * @param class-string<AbstractShipmentAction> $action
     */
    #[DataProvider('actions')]
    public function testARefusalIsShownAsAnErrorAndGoesBack(string $action, string $method): void
    {
        $this->params = ['id' => '41', 'order_id' => '29', 'polkurier_number' => '24680-27'];
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method($method)
            ->willThrowException(new LocalizedException(__('Polkurier refused: the order is already collected.')));

        (new $action($this->context(), $this->requested(), $management))->execute();

        self::assertSame(['error' => ['Polkurier refused: the order is already collected.']], $this->messages);
        self::assertSame(['sales/order/view', ['order_id' => 29]], $this->redirect);
    }

    /**
     * @param class-string<AbstractShipmentAction> $action
     */
    #[DataProvider('actions')]
    public function testAShipmentThatIsGoneIsSaidSo(string $action, string $method): void
    {
        $this->params = ['id' => '999', 'polkurier_number' => '24680-27'];
        $requested = $this->createStub(RequestedShipments::class);
        $requested->method('getOne')
            ->willThrowException(new NoSuchEntityException(__(
                'The Polkurier shipment with id "%1" does not exist.',
                999,
            )));
        $management = $this->createMock(ShipmentManagementInterface::class);
        $management->expects(self::never())->method($method);

        (new $action($this->context(), $requested, $management))->execute();

        self::assertSame(['error' => ['The Polkurier shipment with id "999" does not exist.']], $this->messages);
        self::assertSame(['polkurier/shipment/index', []], $this->redirect);
    }

    private function requested(): RequestedShipments
    {
        $requested = $this->createStub(RequestedShipments::class);
        $requested->method('getOne')->willReturn($this->shipment(ShipmentInterface::STATE_UNKNOWN, null));

        return $requested;
    }

    private function shipment(string $state, ?string $number, ?string $lastError = null): ShipmentInterface
    {
        $shipment = $this->createStub(ShipmentInterface::class);
        $shipment->method('getId')->willReturn(41);
        $shipment->method('getOrderId')->willReturn(29);
        $shipment->method('getState')->willReturn($state);
        $shipment->method('getPolkurierNumber')->willReturn($number);
        $shipment->method('getLastError')->willReturn($lastError);

        return $shipment;
    }
}
