<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Plugin\Quote;

use Magento\Framework\Api\AttributeValueFactory;
use Magento\Framework\Api\ExtensionAttributesFactory;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;
use Magento\Quote\Api\Data\ShippingMethodExtensionFactory;
use Magento\Quote\Api\Data\ShippingMethodExtensionInterface;
use Magento\Quote\Model\Cart\ShippingMethod;
use Magento\Quote\Model\Cart\ShippingMethodConverter;
use Magento\Quote\Model\Quote\Address\Rate;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Carrier\CarrierLogoStorage;
use Polkurier\Shipping\Model\CarrierConfig;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigProvider;
use Polkurier\Shipping\Plugin\Quote\ShippingMethodLogoPlugin;

#[CoversClass(ShippingMethodLogoPlugin::class)]
#[CoversClass(CarrierLogoStorage::class)]
#[AllowMockObjectsWithoutExpectations]
class ShippingMethodLogoPluginTest extends TestCase
{
    public function testTheCarriersLogoTravelsWithItsMethod(): void
    {
        $method = $this->createMethod('polkurier', 'DPD');

        $this->createPlugin('shipping/carrier_logos/dpd.png')->afterModelToDataObject(
            $this->createStub(ShippingMethodConverter::class),
            $method,
            $this->createRate(),
            'PLN',
        );

        self::assertSame(
            'https://shop.example/media/shipping/carrier_logos/dpd.png',
            $method->getExtensionAttributes()?->getMethodLogo(),
        );
    }

    public function testMethodsWithoutALogoAndOtherCarriersAreLeftAlone(): void
    {
        $withoutLogo = $this->createMethod('polkurier', 'DPD');
        $other = $this->createMethod('flatrate', 'flatrate');

        $this->createPlugin(null)->afterModelToDataObject(
            $this->createStub(ShippingMethodConverter::class),
            $withoutLogo,
            $this->createRate(),
            'PLN',
        );
        $this->createPlugin('shipping/carrier_logos/dpd.png')->afterModelToDataObject(
            $this->createStub(ShippingMethodConverter::class),
            $other,
            $this->createRate(),
            'PLN',
        );

        self::assertNull($withoutLogo->getExtensionAttributes()?->getMethodLogo());
        self::assertNull($other->getExtensionAttributes()?->getMethodLogo());
    }

    public function testOnlyRasterImagesInTheLogoDirectoryAreAccepted(): void
    {
        $logo = $this->createLogo();

        self::assertTrue($logo->isOwnFile('shipping/carrier_logos/dpd.png'));
        self::assertFalse($logo->isOwnFile('shipping/carrier_logos/../../app/etc/env.php'));
        self::assertFalse($logo->isOwnFile('wysiwyg/dpd.png'));
        self::assertFalse($logo->isOwnFile('shipping/carrier_logos/dpd.svg'), 'An SVG can carry scripts.');
    }

    private function createPlugin(?string $file): ShippingMethodLogoPlugin
    {
        $config = (new CarrierConfig(
            $this->createStub(\Magento\Framework\Model\Context::class),
            $this->createStub(\Magento\Framework\Registry::class),
            new \Magento\Framework\Serialize\Serializer\Json(),
            $this->createStub(\Polkurier\Shipping\Model\ResourceModel\CarrierConfig::class),
        ))->setLogo($file);
        $configs = $this->createStub(CarrierConfigProvider::class);
        $configs->method('get')->willReturn($config);
        $extensions = $this->createStub(ShippingMethodExtensionFactory::class);
        $extensions->method('create')->willReturnCallback(fn() => $this->createExtension());

        return new ShippingMethodLogoPlugin($extensions, $configs, $this->createLogo(), $this->storeManager());
    }

    /**
     * The extension attributes of a shipping method, as Magento generates them for the module:
     * the generated class depends on configuration unit tests don't load.
     */
    private function createExtension(): ShippingMethodExtensionInterface
    {
        return new class implements ShippingMethodExtensionInterface {
            private ?string $logo = null;

            public function getMethodLogo(): ?string
            {
                return $this->logo;
            }

            /**
             * @param string|null $methodLogo
             */
            public function setMethodLogo($methodLogo): static
            {
                $this->logo = $methodLogo;

                return $this;
            }
        };
    }

    private function createLogo(): CarrierLogoStorage
    {
        return new CarrierLogoStorage(
            $this->createStub(UploaderFactory::class),
            $this->createStub(Filesystem::class),
            $this->storeManager(),
            $this->createStub(Logger::class),
        );
    }

    private function storeManager(): StoreManagerInterface
    {
        $store = $this->createStub(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.example/media/');
        $store->method('getId')->willReturn(1);
        $manager = $this->createStub(StoreManagerInterface::class);
        $manager->method('getStore')->willReturn($store);

        return $manager;
    }

    private function createMethod(string $carrier, string $code): ShippingMethod
    {
        $extensions = $this->createStub(ExtensionAttributesFactory::class);
        $extensions->method('create')->willReturnCallback(fn() => $this->createExtension());
        $method = new ShippingMethod($extensions, $this->createStub(AttributeValueFactory::class));

        return $method->setCarrierCode($carrier)->setMethodCode($code);
    }

    private function createRate(): Rate
    {
        return $this->createStub(Rate::class);
    }
}
