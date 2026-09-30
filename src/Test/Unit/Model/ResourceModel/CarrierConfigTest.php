<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\CarrierLabelInterfaceFactory;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\CarrierLabel;
use Polkurier\Shipping\Model\ResourceModel\CarrierConfig as Resource;

#[CoversClass(Resource::class)]
#[AllowMockObjectsWithoutExpectations]
class CarrierConfigTest extends TestCase
{
    /**
     * @var list<string> writes to the label table, in order
     */
    private array $writes = [];

    public function testLabelsSavedAsTheyWereLoadedAreNotWrittenAgain(): void
    {
        $resource = $this->resource();
        $config = $this->config();
        $resource->attachStoreLabels($config, [$this->label(1, 'Kurier')]);

        $config->setStoreLabels([$this->label(1, 'Kurier')]);
        $this->afterSave($resource, $config);

        self::assertSame([], $this->writes);
    }

    public function testChangedLabelsReplaceTheStoredOnesOnce(): void
    {
        $resource = $this->resource();
        $config = $this->config();
        $resource->attachStoreLabels($config, [$this->label(1, 'Kurier')]);

        $config->setStoreLabels([$this->label(1, 'Kurier DPD')]);
        $this->afterSave($resource, $config);
        $this->afterSave($resource, $config);

        self::assertSame(['delete', 'insert'], $this->writes);
    }

    public function testRemovingEveryLabelDeletesThem(): void
    {
        $resource = $this->resource();
        $config = $this->config();
        $resource->attachStoreLabels($config, [$this->label(1, 'Kurier')]);

        $config->setStoreLabels([]);
        $this->afterSave($resource, $config);

        self::assertSame(['delete'], $this->writes);
    }

    private function resource(): Resource
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('delete')->willReturnCallback(function (): int {
            $this->writes[] = 'delete';

            return 1;
        });
        $connection->method('insertMultiple')->willReturnCallback(function (): int {
            $this->writes[] = 'insert';

            return 1;
        });
        $resources = $this->createStub(ResourceConnection::class);
        $resources->method('getConnection')->willReturn($connection);
        $resources->method('getTableName')->willReturnArgument(0);
        $context = $this->createStub(Context::class);
        $context->method('getResources')->willReturn($resources);

        return new Resource($context, $this->createStub(CarrierLabelInterfaceFactory::class));
    }

    private function config(): CarrierConfig
    {
        $config = (new ObjectManager($this))->getObject(CarrierConfig::class);
        $config->setId(7);

        return $config;
    }

    private function label(int $storeId, string $label): CarrierLabel
    {
        return (new CarrierLabel())->setStoreId($storeId)->setLabel($label);
    }

    private function afterSave(Resource $resource, CarrierConfig $config): void
    {
        (new \ReflectionMethod($resource, '_afterSave'))->invoke($resource, $config);
    }
}
