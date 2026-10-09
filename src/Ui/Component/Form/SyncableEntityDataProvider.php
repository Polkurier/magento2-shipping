<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Model\Sync\EntityHandlerInterface;

/**
 * Data of the address, bank account and pack template forms. The entry's key is exposed as "id",
 * whatever its column is called; the synchronization state is shown read-only.
 */
class SyncableEntityDataProvider extends FormDataProvider
{
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
        private readonly EntityHandlerInterface $entityHandler,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collection;
    }

    protected function load(): array
    {
        $loaded = [];
        $id = (int)$this->request->getParam('id');
        if ($id) {
            $this->collection->addFieldToFilter($this->collection->getResource()->getIdFieldName(), ['eq' => $id]);
            foreach ($this->collection->getItems() as $item) {
                /** @var SyncableInterface&\Magento\Framework\Model\AbstractModel $item */
                $loaded[$id] = \array_merge($item->getData(), ['id' => $id]);
            }
        }
        $persisted = $this->dataPersistor->get($this->entityHandler->getFormMemory()->value);
        if (\is_array($persisted)) {
            $loaded[$id ?: ''] = \array_merge($loaded[$id] ?? [], $persisted, ['id' => $id ?: null]);
            $this->dataPersistor->clear($this->entityHandler->getFormMemory()->value);
        }

        return $loaded;
    }
}
