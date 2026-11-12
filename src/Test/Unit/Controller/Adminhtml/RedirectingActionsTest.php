<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Ui\Component\MassAction\Filter;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Controller\Adminhtml\Carrier\Edit;
use Polkurier\Shipping\Controller\Adminhtml\Carrier\MassToggle;
use Polkurier\Shipping\Controller\Adminhtml\Payout\Cancel;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Create;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\Payout\PayoutManager;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\Collection;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig\CollectionFactory;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Placement\FailedShipmentManager;
use Polkurier\Shipping\Model\Shipment\Screen\RetriableShipmentFinder;
use Polkurier\Shipping\Test\Unit\Support\AdminActionTestCase;

#[CoversClass(Create::class)]
#[CoversClass(Edit::class)]
#[CoversClass(Cancel::class)]
#[CoversClass(MassToggle::class)]
#[AllowMockObjectsWithoutExpectations]
class RedirectingActionsTest extends AdminActionTestCase
{
    public function testAnOrderOfAnotherCarrierGoesBackToTheOrder(): void
    {
        $this->params = ['order_id' => '30'];
        $orders = $this->createStub(OrderLoader::class);
        $orders->method('getForPolkurier')
            ->willThrowException(new LocalizedException(__(
                'Order #000000052 wasn\'t placed with a Polkurier '
                . 'delivery method, so it isn\'t shipped with Polkurier.',
            )));

        (new Create(
            $this->createContext(),
            $orders,
            $this->createStub(RetriableShipmentFinder::class),
            $this->createStub(FailedShipmentManager::class),
        ))->execute();

        self::assertSame(['sales/order/view', ['order_id' => 30]], $this->redirect);
        self::assertStringStartsWith(
            'Order #000000052 wasn\'t placed with a Polkurier delivery method',
            $this->messages['error'][0],
        );
    }

    public function testACarrierThatIsGoneGoesBackToTheList(): void
    {
        $this->params = ['id' => '999'];
        $repository = $this->createStub(CarrierConfigRepositoryInterface::class);
        $repository->method('get')
            ->willThrowException(new NoSuchEntityException(__(
                'The carrier settings with id "%1" do not exist.',
                999,
            )));

        (new Edit($this->createContext(), $repository))->execute();

        self::assertSame(['*/*/index', []], $this->redirect);
        self::assertSame(['The carrier settings with id "999" do not exist.'], $this->messages['error']);
    }

    public function testTheChosenCarriersAreEachSwitchedTheOtherWay(): void
    {
        $dpd = $this->createCarrier('DPD', true);
        $inpost = $this->createCarrier('INPOST', false);
        $ups = $this->createCarrier('UPS', false);
        $filter = $this->createStub(Filter::class);
        $filter->method('getCollection')->willReturn([$dpd, $inpost, $ups]);
        $collections = $this->createStub(CollectionFactory::class);
        $collections->method('create')->willReturn($this->createStub(Collection::class));
        $saved = [];
        $repository = $this->createStub(CarrierConfigRepositoryInterface::class);
        $repository->method('save')
            ->willReturnCallback(static function (CarrierConfigInterface $config) use (&$saved): CarrierConfigInterface {
                $saved[] = $config->getCarrierCode();

                return $config;
            });

        (new MassToggle($this->createContext(), $filter, $collections, $repository))->execute();

        self::assertSame([false, true, true], [$dpd->isActive(), $inpost->isActive(), $ups->isActive()]);
        self::assertSame(['DPD', 'INPOST', 'UPS'], $saved);
        self::assertSame(['2 enabled, 1 disabled.'], $this->messages['success']);
        self::assertSame(['*/*/index', []], $this->redirect);
    }

    public function testCancellingNoPayoutGoesBackToTheList(): void
    {
        $this->params = ['number' => ''];
        $payouts = $this->createMock(PayoutManager::class);
        $payouts->expects(self::never())->method('cancelPayout');

        (new Cancel($this->createContext(), $payouts))->execute();

        self::assertSame(['*/*/index', []], $this->redirect);
        self::assertSame(['No payout to cancel.'], $this->messages['error']);
    }

    public function testACancelledPayoutIsReported(): void
    {
        $this->params = ['number' => 'W-1'];
        $payouts = $this->createMock(PayoutManager::class);
        $payouts->expects(self::once())->method('cancelPayout')->with('W-1');

        (new Cancel($this->createContext(), $payouts))->execute();

        self::assertSame(['*/*/index', []], $this->redirect);
        self::assertSame(['Payout W-1 cancelled.'], $this->messages['success']);
    }

    private function createCarrier(string $code, bool $active): CarrierConfig
    {
        /** @var CarrierConfig $config */
        $config = (new ObjectManager($this))->getObject(CarrierConfig::class);

        return $config->setCarrierCode($code)->setActive($active);
    }
}
