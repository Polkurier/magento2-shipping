<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\ObjectManagerInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * @property AbstractCollection $collection
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var array
     */
    private array $loadedData = [];

    /**
     * @param DataPersistorInterface $dataPersistor
     * @param ObjectManagerInterface $objectManager
     * @param string $collectionType
     * @param string $dataPersistorKey
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        /**
         * @var DataPersistorInterface
         */
        private readonly DataPersistorInterface $dataPersistor,

        /**
         * @var string
         */
        private readonly string $dataPersistorKey,

        ObjectManagerInterface $objectManager,
        string $collectionType,
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        array $meta = [],
        array $data = []
    ) {
        $this->collection = $objectManager->create($collectionType);
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        if ($this->loadedData) {
            return $this->loadedData;
        }

        /** @var AbstractModel $model */
        foreach ($this->collection->getItems() as $model) {
            $this->loadedData[$model->getId()] = $model->getData();
        }

        $persistedData = $this->dataPersistor->get($this->dataPersistorKey);
        if (!empty($persistedData)) {
            $model = $this->collection->getNewEmptyItem();
            $model->setData($persistedData);
            $this->loadedData[$model->getId() ?: 0] = $model->getData();
            $this->dataPersistor->clear($this->dataPersistorKey);
        }

        return $this->loadedData;
    }
}
