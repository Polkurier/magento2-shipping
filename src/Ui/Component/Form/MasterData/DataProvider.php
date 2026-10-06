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
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Ui\Component\Form\FormDataProvider;

/**
 * Data of the address, bank account and pack template forms. The entry's key is exposed as "id",
 * whatever its column is called; the synchronization state is shown read-only. Fields stored as
 * comma-separated lists (listFields, given per form in di.xml) reach the form as lists.
 */
class DataProvider extends FormDataProvider
{
    /**
     * @param array<string, mixed> $meta
     * @param array<string, mixed> $data
     * @param string[] $listFields
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
        private readonly array $listFields = [],
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
                $data = $item->getData();
                foreach ($this->listFields as $field) {
                    if (isset($data[$field]) && \is_string($data[$field])) {
                        $data[$field] = \array_values(\array_filter(\explode(',', $data[$field])));
                    }
                }
                $loaded[$id] = \array_merge($data, ['id' => $id]);
            }
        }
        $persisted = $this->dataPersistor->get($this->persistorKey);
        if (\is_array($persisted)) {
            $loaded[$id ?: ''] = \array_merge($loaded[$id] ?? [], $persisted, ['id' => $id ?: null]);
            $this->dataPersistor->clear($this->persistorKey);
        }

        return $loaded;
    }
}
