<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\CarrierConfig;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Model\Carrier\CarrierCatalog;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigSynchronizer;
use Polkurier\Shipping\Model\CarrierConfigFactory;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig as CarrierConfigResource;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Webservice\Dto\Response\Carrier;
use Polkurier\Webservice\Exception\ConnectionException;

#[CoversClass(CarrierConfigSynchronizer::class)]
#[AllowMockObjectsWithoutExpectations]
class CarrierConfigSynchronizerTest extends TestCase
{
    /**
     * @var array<string, CarrierConfigInterface> the rows, by carrier code
     */
    private array $rows = [];

    /**
     * @var string[]
     */
    private array $saved = [];

    /**
     * @var string[]
     */
    private array $notices = [];

    /**
     * @var string[]
     */
    private array $transaction = [];

    public function testTheFirstReadAddsEveryCarrierSwitchedOffInOrderWithoutTellingAnyone(): void
    {
        self::assertSame(2, $this->createSynchronizer(['DPD', 'INPOST_PACZKOMAT'])->synchronize());

        self::assertSame(['DPD', 'INPOST_PACZKOMAT'], $this->saved);
        self::assertFalse($this->rows['DPD']->isActive());
        self::assertTrue($this->rows['DPD']->isAvailable());
        self::assertSame(
            [10, 20],
            [$this->rows['DPD']->getSortOrder(), $this->rows['INPOST_PACZKOMAT']->getSortOrder()],
        );
        self::assertSame([], $this->notices, 'Everything is new on the first read: nothing is news.');
        self::assertSame(['begin', 'commit'], $this->transaction);
    }

    public function testANewCarrierGoesLastAndIsToldAbout(): void
    {
        $this->addRow('DPD', 30, active: true);

        $this->createSynchronizer(['DPD', 'UPS'])->synchronize();

        self::assertSame(40, $this->rows['UPS']->getSortOrder());
        self::assertTrue($this->rows['DPD']->isActive(), 'The merchant\'s settings stay.');
        self::assertSame(['notice: UPS'], $this->notices);
    }

    public function testACarrierNoLongerOfferedKeepsItsRowUnavailableAndIsToldAbout(): void
    {
        $this->addRow('DPD', 10, active: true);
        $this->addRow('UPS', 20, active: true);

        $this->createSynchronizer(['DPD'])->synchronize();

        self::assertFalse($this->rows['UPS']->isAvailable());
        self::assertTrue($this->rows['UPS']->isActive(), 'Back as it was if the account offers it again.');
        self::assertSame(['major: UPS'], $this->notices);
    }

    public function testACarrierOfferedAgainIsToldAboutAsNew(): void
    {
        $this->addRow('DPD', 10);
        $this->addRow('UPS', 20, available: false);

        $this->createSynchronizer(['DPD', 'UPS'])->synchronize();

        self::assertTrue($this->rows['UPS']->isAvailable());
        self::assertSame(20, $this->rows['UPS']->getSortOrder());
        self::assertSame(['notice: UPS'], $this->notices);
    }

    public function testWhenPolkurierCantBeAskedNothingChanges(): void
    {
        $this->addRow('DPD', 10);

        try {
            $this->createSynchronizer(new ConnectionException('Timed out'))->synchronize();
            self::fail('The failure is said.');
        } catch (LocalizedException) {
        }

        self::assertSame([], $this->saved);
        self::assertSame([], $this->transaction, 'Asked before anything is touched.');
    }

    public function testARowThatCantBeSavedTakesTheOthersBackWithIt(): void
    {
        try {
            $this->createSynchronizer(['DPD', 'UPS'], failOn: 'UPS')->synchronize();
            self::fail('The failure is said.');
        } catch (CouldNotSaveException) {
        }

        self::assertSame(['begin', 'rollBack'], $this->transaction);
        self::assertSame([], $this->notices);
    }

    private function addRow(string $code, int $sortOrder, bool $active = false, bool $available = true): void
    {
        $this->rows[$code] = $this->createConfig()
            ->setCarrierCode($code)
            ->setCarrierName($code)
            ->setSortOrder($sortOrder)
            ->setActive($active)
            ->setAvailable($available);
    }

    private function createConfig(): CarrierConfig
    {
        return new CarrierConfig(
            $this->createStub(Context::class),
            $this->createStub(Registry::class),
            new Json(),
            $this->createStub(CarrierConfigResource::class),
        );
    }

    /**
     * @param string[]|\Throwable $offered the account's carriers, or why they can't be read
     */
    private function createSynchronizer(array|\Throwable $offered, ?string $failOn = null): CarrierConfigSynchronizer
    {
        $catalog = $this->createStub(CarrierCatalog::class);
        if ($offered instanceof \Throwable) {
            $catalog->method('refreshAccountCarriers')->willThrowException($offered);
        } else {
            $carriers = [];
            foreach ($offered as $code) {
                $carriers[$code] = new Carrier($code, $code, false);
            }
            $catalog->method('refreshAccountCarriers')->willReturn($carriers);
        }
        $existing = $this->rows;
        $configs = $this->createStub(CarrierConfigProvider::class);
        $configs->method('getAll')->willReturnCallback(static fn(): array => $existing);
        $repository = $this->createStub(CarrierConfigRepositoryInterface::class);
        $repository->method('save')
            ->willReturnCallback(function (CarrierConfigInterface $config) use ($failOn): CarrierConfigInterface {
                if ($config->getCarrierCode() === $failOn) {
                    throw new CouldNotSaveException(__('Deadlock'));
                }
                $this->saved[] = (string)$config->getCarrierCode();
                $this->rows[(string)$config->getCarrierCode()] = $config;

                return $config;
            });
        $factory = $this->createStub(CarrierConfigFactory::class);
        $factory->method('create')->willReturnCallback(fn(): CarrierConfig => $this->createConfig());
        $connection = $this->createStub(AdapterInterface::class);
        foreach (['beginTransaction' => 'begin', 'commit' => 'commit', 'rollBack' => 'rollBack'] as $method => $step) {
            $connection->method($method)->willReturnCallback(function () use ($step, $connection): AdapterInterface {
                $this->transaction[] = $step;

                return $connection;
            });
        }
        $resource = $this->createStub(CarrierConfigResource::class);
        $resource->method('getConnection')->willReturn($connection);
        $notifier = $this->createStub(NotifierInterface::class);
        foreach (['addNotice' => 'notice', 'addMajor' => 'major'] as $method => $kind) {
            $notifier->method($method)->willReturnCallback(
                function (string $title, string $description) use ($kind, $notifier): NotifierInterface {
                    \preg_match('/offers ([A-Z_, ]+)\./', $description, $match);
                    $this->notices[] = $kind . ': ' . ($match[1] ?? $description);

                    return $notifier;
                },
            );
        }

        return new CarrierConfigSynchronizer(
            $catalog,
            $configs,
            $repository,
            $factory,
            $resource,
            new ErrorMessageResolver(),
            $notifier,
            $this->createStub(UrlInterface::class),
        );
    }
}
