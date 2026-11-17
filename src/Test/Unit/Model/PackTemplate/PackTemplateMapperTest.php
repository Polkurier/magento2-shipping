<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\PackTemplate;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Model\PackTemplate;
use Polkurier\Shipping\Model\PackTemplate\PackTemplateMapper;
use Polkurier\Shipping\Model\Shipment\Placement\ParcelTypeMapper;
use Polkurier\Shipping\Test\Unit\Support\NewingObjectManager;
use Polkurier\Webservice\Builder\Entity\PackTemplateBuilderFactory;
use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;

#[CoversClass(PackTemplateMapper::class)]
#[AllowMockObjectsWithoutExpectations]
class PackTemplateMapperTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function providePackTypes(): array
    {
        return [
            'a parcel' => [PackTypeEnum::Standard->value],
            'a non-standard parcel' => [PackTypeEnum::NonStandard->value],
            'an envelope' => [ParcelTypeMapper::ENVELOPE],
            'a EURO pallet' => [ParcelTypeMapper::EURO_PALLET],
            'a non-standard EURO pallet' => [ParcelTypeMapper::EURO_PALLET_NON_STANDARD],
            'a half pallet' => [PackTypeEnum::HalfPalette->value],
        ];
    }

    #[DataProvider('providePackTypes')]
    public function testATemplateSentToPolkurierComesBackAsItWas(string $packType): void
    {
        $mapper = self::createMapper();
        $template = $this->createTemplate()
            ->setExtId('pt-9')
            ->setName('Karton M')
            ->setDescription('Na buty')
            ->setPackType($packType)
            ->setLength(40)
            ->setWidth(30)
            ->setHeight(20)
            ->setWeight(2.5)
            ->setCodAmount(150.0)
            ->setInsuranceAmount(500.0)
            ->setCarrierCode('DPD')
            ->setServices([AdditionalServiceEnum::ReturnOfDocuments->value]);

        $copy = $this->createTemplate();
        $mapper->apply($mapper->normalize($mapper->mapToRecord($template)), $copy);

        foreach ([
            PackTemplateInterface::FIELD_EXT_ID, PackTemplateInterface::FIELD_NAME,
            PackTemplateInterface::FIELD_DESCRIPTION, PackTemplateInterface::FIELD_PACK_TYPE,
            PackTemplateInterface::FIELD_LENGTH, PackTemplateInterface::FIELD_WIDTH,
            PackTemplateInterface::FIELD_HEIGHT, PackTemplateInterface::FIELD_WEIGHT,
            PackTemplateInterface::FIELD_COD_AMOUNT, PackTemplateInterface::FIELD_INSURANCE_AMOUNT,
            PackTemplateInterface::FIELD_CARRIER_CODE, PackTemplateInterface::FIELD_SERVICES,
        ] as $field) {
            self::assertSame($template->getData($field), $copy->getData($field), $field);
        }
    }

    public function testATemplateIsSentWithItsShipmentTypeAndOnlyTheServicesTheSdkKnows(): void
    {
        $template = $this->createTemplate()
            ->setName('Paleta')
            ->setPackType(ParcelTypeMapper::EURO_PALLET_NON_STANDARD)
            ->setServices(['NO_SUCH_SERVICE', AdditionalServiceEnum::ReturnOfDocuments->value]);

        $record = self::createMapper()->mapToRecord($template);

        self::assertSame(ShipmentTypeEnum::Palette, $record->getShipmentType());
        self::assertSame(PackTypeEnum::NonStandard, $record->getType());
        self::assertSame([AdditionalServiceEnum::ReturnOfDocuments], $record->getServices());
        self::assertNull($record->getCourier(), 'Without a carrier, the template is for any.');
    }

    public function testATemplateIsDeletedByItsIdInPolkurier(): void
    {
        $message = self::createMapper()->mapToDeleteMessage($this->createTemplate()->setExtId('pt-9'));

        self::assertSame('pt-9', $message->id);
    }

    private static function createMapper(): PackTemplateMapper
    {
        return new PackTemplateMapper(NewingObjectManager::createFactory(PackTemplateBuilderFactory::class));
    }

    private function createTemplate(): PackTemplate
    {
        /** @var PackTemplate $template */
        $template = (new ObjectManager($this))->getObject(PackTemplate::class);

        return $template;
    }
}
