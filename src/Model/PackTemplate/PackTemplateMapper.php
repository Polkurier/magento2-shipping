<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\PackTemplate;

use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Model\Shipment\Placement\ParcelTypeMapper;
use Polkurier\Webservice\Builder\PackTemplate\SaveMessageBuilderFactory;
use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;
use Polkurier\Webservice\Request\Common\DeleteMessage;
use Polkurier\Webservice\Request\PackTemplate\SaveMessage;
use Polkurier\Webservice\Response\PackTemplate;

/**
 * Maps pack templates between Magento and the Polkurier account.
 */
readonly class PackTemplateMapper
{
    public function __construct(
        private SaveMessageBuilderFactory $saveMessageBuilderFactory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function normalize(PackTemplate $template): array
    {
        $services = \array_map(
            static fn(mixed $service): string => $service instanceof AdditionalServiceEnum
                ? $service->value
                : (string)$service,
            \array_values($template->getServices()),
        );
        \sort($services);

        return [
            PackTemplateInterface::FIELD_EXT_ID => $template->getId(),
            PackTemplateInterface::FIELD_NAME => $template->getName(),
            PackTemplateInterface::FIELD_DESCRIPTION => $template->getDescription() ?: null,
            PackTemplateInterface::FIELD_PACK_TYPE => ParcelTypeMapper::fromPolkurier(
                $template->getShipmentType(),
                $template->getType(),
            ),
            PackTemplateInterface::FIELD_LENGTH => $template->getLength(),
            PackTemplateInterface::FIELD_WIDTH => $template->getWidth(),
            PackTemplateInterface::FIELD_HEIGHT => $template->getHeight(),
            PackTemplateInterface::FIELD_WEIGHT => $template->getWeight(),
            PackTemplateInterface::FIELD_COD_AMOUNT => $template->getCod(),
            PackTemplateInterface::FIELD_INSURANCE_AMOUNT => $template->getInsurance(),
            PackTemplateInterface::FIELD_CARRIER_CODE => $template->getCourier() ?: null,
            PackTemplateInterface::FIELD_SERVICES => $services,
        ];
    }

    /**
     * @param array<string, mixed> $remote
     */
    public function apply(array $remote, PackTemplateInterface $template): void
    {
        $extId = $remote[PackTemplateInterface::FIELD_EXT_ID] ?? null;
        $template->setExtId($extId !== null ? (string)$extId : null)
            ->setName((string)$remote[PackTemplateInterface::FIELD_NAME])
            ->setDescription($remote[PackTemplateInterface::FIELD_DESCRIPTION])
            ->setPackType(ParcelTypeMapper::normalize((string)$remote[PackTemplateInterface::FIELD_PACK_TYPE]))
            ->setLength((int)$remote[PackTemplateInterface::FIELD_LENGTH])
            ->setWidth((int)$remote[PackTemplateInterface::FIELD_WIDTH])
            ->setHeight((int)$remote[PackTemplateInterface::FIELD_HEIGHT])
            ->setWeight((float)$remote[PackTemplateInterface::FIELD_WEIGHT])
            ->setCodAmount((float)$remote[PackTemplateInterface::FIELD_COD_AMOUNT])
            ->setInsuranceAmount((float)$remote[PackTemplateInterface::FIELD_INSURANCE_AMOUNT])
            ->setCarrierCode($remote[PackTemplateInterface::FIELD_CARRIER_CODE])
            ->setServices((array)$remote[PackTemplateInterface::FIELD_SERVICES]);
    }

    public function toSaveMessage(PackTemplateInterface $template): SaveMessage
    {
        return $this->saveMessageBuilderFactory->create()
            ->name($template->getName())
            ->length($template->getLength())
            ->width($template->getWidth())
            ->height($template->getHeight())
            ->weight($template->getWeight())
            ->shipmentType(ParcelTypeMapper::shipmentTypeOf($template->getPackType()))
            ->type(ParcelTypeMapper::polkurierTypeOf($template->getPackType()))
            ->id($template->getExtId())
            ->cod($template->getCodAmount())
            ->insurance($template->getInsuranceAmount())
            ->description((string)$template->getDescription())
            ->courier($template->getCarrierCode() ?: null)
            ->services(\array_values(\array_filter(\array_map(
                static fn(string $service): ?AdditionalServiceEnum => AdditionalServiceEnum::tryFrom($service),
                $template->getServices(),
            ))))
            ->build();
    }

    public function toDeleteMessage(PackTemplateInterface $template): DeleteMessage
    {
        return new DeleteMessage(id: (string)$template->getExtId());
    }
}
