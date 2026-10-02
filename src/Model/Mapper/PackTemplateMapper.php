<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Mapper;

use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Model\Shipment\PackKinds;
use Polkurier\Webservice\Enum\Order\AdditionalServiceEnum;
use Polkurier\Webservice\Request\Common\DeleteMessage;
use Polkurier\Webservice\Request\PackTemplate\SaveMessage;
use Polkurier\Webservice\Response\PackTemplate;

/**
 * Maps pack templates between Magento and the Polkurier account.
 */
class PackTemplateMapper
{
    /**
     * @return array<string, mixed>
     */
    public function normalize(PackTemplate $template): array
    {
        $services = \array_map(
            static fn(mixed $service): string => $service instanceof AdditionalServiceEnum ? $service->value : (string)$service,
            \array_values($template->getServices()),
        );
        \sort($services);

        return [
            'id' => $template->getId(),
            'name' => $template->getName(),
            'description' => $template->getDescription() ?: null,
            'pack_type' => PackKinds::fromPolkurier($template->getShipmentType(), $template->getType()),
            'length' => $template->getLength(),
            'width' => $template->getWidth(),
            'height' => $template->getHeight(),
            'weight' => $template->getWeight(),
            'cod' => $template->getCod(),
            'insurance' => $template->getInsurance(),
            'carrier_code' => $template->getCourier() ?: null,
            'services' => $services,
        ];
    }

    /**
     * @param array<string, mixed> $remote
     */
    public function apply(array $remote, PackTemplateInterface $template): void
    {
        $template->setExtId(isset($remote['id']) ? (string)$remote['id'] : null)
            ->setName((string)$remote['name'])
            ->setDescription($remote['description'])
            ->setPackType(PackKinds::normalize((string)$remote['pack_type']))
            ->setLength((int)$remote['length'])
            ->setWidth((int)$remote['width'])
            ->setHeight((int)$remote['height'])
            ->setWeight((float)$remote['weight'])
            ->setCodAmount((float)$remote['cod'])
            ->setInsuranceAmount((float)$remote['insurance'])
            ->setCarrierCode($remote['carrier_code'])
            ->setServices((array)$remote['services']);
    }

    public function toSaveMessage(PackTemplateInterface $template): SaveMessage
    {
        return new SaveMessage(
            name: $template->getName(),
            length: $template->getLength(),
            width: $template->getWidth(),
            height: $template->getHeight(),
            weight: $template->getWeight(),
            shipmentType: PackKinds::shipmentTypeOf($template->getPackType()),
            type: PackKinds::polkurierTypeOf($template->getPackType()),
            id: $template->getExtId(),
            cod: $template->getCodAmount(),
            insurance: $template->getInsuranceAmount(),
            description: (string)$template->getDescription(),
            courier: $template->getCarrierCode() ?: null,
            services: \array_values(\array_filter(\array_map(
                static fn(string $service): ?AdditionalServiceEnum => AdditionalServiceEnum::tryFrom($service),
                $template->getServices(),
            ))),
        );
    }

    public function toDeleteMessage(PackTemplateInterface $template): DeleteMessage
    {
        return new DeleteMessage(id: (string)$template->getExtId());
    }
}
