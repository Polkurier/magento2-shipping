<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\MasterData;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Polkurier\Shipping\Api\Data\SyncableInterface;

/**
 * Data of the address, bank account and pack template forms. The entry's key is exposed as "id",
 * whatever its column is called; the synchronization state is shown read-only.
 */
class DataProvider extends AbstractDataProvider
{
    /**
     * @var array<int|string, array<string, mixed>>|null
     */
    private ?array $loadedData = null;

    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        AbstractCollection $collection,
        private readonly RequestInterface $request,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly string $persistorKey,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collection;
    }

    /**
     * The form narrows by its own request parameter, not through the collection.
     */
    public function addFilter(Filter $filter): void
    {
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function getData(): array
    {
        if ($this->loadedData !== null) {
            return $this->loadedData;
        }
        $this->loadedData = [];
        $id = (int)$this->request->getParam('id');
        if ($id) {
            $this->collection->addFieldToFilter($this->collection->getResource()->getIdFieldName(), ['eq' => $id]);
            foreach ($this->collection->getItems() as $item) {
                /** @var SyncableInterface&\Magento\Framework\Model\AbstractModel $item */
                $data = $item->getData();
                if (isset($data['services']) && \is_string($data['services'])) {
                    $data['services'] = \array_values(\array_filter(\explode(',', $data['services'])));
                }
                $this->loadedData[$id] = \array_merge($data, ['id' => $id]);
            }
        }
        $persisted = $this->dataPersistor->get($this->persistorKey);
        if (\is_array($persisted)) {
            $this->loadedData[$id ?: ''] = \array_merge($this->loadedData[$id] ?? [], $persisted, ['id' => $id ?: null]);
            $this->dataPersistor->clear($this->persistorKey);
        }

        return $this->loadedData;
    }
}
