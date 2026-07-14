<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Repository;

use Magento\Framework\Exception\AlreadyExistsException;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\ExternalEntityRepositoryInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Model\PackTemplate;
use Polkurier\Shipping\Model\PackTemplateFactory;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate as PackTemplateResource;
use Polkurier\Shipping\Model\ResourceModel\PackTemplate\CollectionFactory;

readonly class PackTemplateRepository implements PackTemplateRepositoryInterface, ExternalEntityRepositoryInterface
{
    /**
     * @param PackTemplateFactory $entityFactory
     * @param PackTemplateResource $resource
     * @param CollectionFactory $collectionFactory
     */
    public function __construct(
        /**
         * @var PackTemplateFactory
         */
        private PackTemplateFactory $entityFactory,

        /**
         * @var PackTemplateResource
         */
        private PackTemplateResource $resource,

        /**
         * @var CollectionFactory
         */
        private CollectionFactory $collectionFactory,
    ) {}

    /**
     * @return iterable<PackTemplateInterface>
     */
    public function findAll(): iterable
    {
        return $this->collectionFactory->create()->load();
    }

    /**
     * @param int $id
     *
     * @return PackTemplateInterface|null
     */
    public function findOneById(int $id): ?PackTemplateInterface
    {
        $template = $this->entityFactory->create();
        $this->resource->load($template, $id);

        return $template->getId() ? $template : null;
    }

    /**
     * @return PackTemplateInterface|null
     */
    public function findOneDefault(): ?PackTemplateInterface
    {
        $template = $this->entityFactory->create();
        $this->resource->load($template, 1, PackTemplate::FIELD_DEFAULT);

        return $template->getId() ? $template : null;
    }

    /**
     * @param PackTemplateInterface $packTemplate
     *
     * @return PackTemplateInterface
     * @throws AlreadyExistsException
     */
    public function save(PackTemplateInterface $packTemplate): PackTemplateInterface
    {
        $this->resource->save($packTemplate);

        return $packTemplate;
    }

    /**
     * @param PackTemplateInterface $packTemplate
     *
     * @return void
     * @throws \Exception
     */
    public function delete(PackTemplateInterface $packTemplate): void
    {
        $this->resource->delete($packTemplate);
    }

    /**
     * @param int $id
     *
     * @return void
     * @throws \Exception
     */
    public function deleteById(int $id): void
    {
        $packTemplate = $this->findOneById($id);
        if ($packTemplate) {
            $this->delete($packTemplate);
        }
    }

    /**
     * @param string $extId
     *
     * @return PackTemplateInterface|null
     */
    public function findOneByExtId(string $extId): ?PackTemplateInterface
    {
        $packTemplate = $this->entityFactory->create();
        $this->resource->load($packTemplate, $extId, PackTemplate::FIELD_EXT_ID);

        return $packTemplate->getId() ? $packTemplate : null;
    }
}
