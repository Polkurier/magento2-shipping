<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\Model\ResourceModel\Db\Context;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Api\Data\CarrierLabelInterface;
use Polkurier\Shipping\Api\Data\CarrierLabelInterfaceFactory;

/**
 * Carrier settings, with their store view labels kept in a table of their own.
 */
class CarrierConfig extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_carrier_config';
    public const string LABEL_TABLE_NAME = 'polkurier_carrier_label';

    public function __construct(
        Context $context,
        private readonly CarrierLabelInterfaceFactory $labelFactory,
        ?string $connectionName = null,
    ) {
        parent::__construct($context, $connectionName);
    }

    /**
     * @param int[] $configIds
     *
     * @return array<int, CarrierLabelInterface[]> keyed by config id
     */
    public function loadStoreLabels(array $configIds): array
    {
        if ($configIds === []) {
            return [];
        }
        $connection = $this->getConnection();
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->getTable(self::LABEL_TABLE_NAME))
                ->where(CarrierConfigInterface::FIELD_CONFIG_ID . ' IN (?)', $configIds)
                ->order(CarrierLabelInterface::FIELD_STORE_ID),
        );
        $labels = [];
        foreach ($rows as $row) {
            $labels[(int)$row[CarrierConfigInterface::FIELD_CONFIG_ID]][] = $this->labelFactory->create()
                ->setStoreId((int)$row[CarrierLabelInterface::FIELD_STORE_ID])
                ->setLabel((string)$row[CarrierLabelInterface::FIELD_LABEL]);
        }

        return $labels;
    }

    /**
     * Sets loaded labels as both the current and the original data, so a save can tell whether
     * anything changed them.
     *
     * @param CarrierLabelInterface[] $labels
     */
    public function attachStoreLabels(AbstractModel $object, array $labels): void
    {
        $object->setData(CarrierConfigInterface::FIELD_STORE_LABELS, $labels);
        $object->setOrigData(CarrierConfigInterface::FIELD_STORE_LABELS, $labels);
    }

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, CarrierConfigInterface::FIELD_CONFIG_ID);
    }

    protected function _afterLoad(AbstractModel $object): static
    {
        if ($object->getId()) {
            $this->attachStoreLabels(
                $object,
                $this->loadStoreLabels([(int)$object->getId()])[(int)$object->getId()] ?? [],
            );
        }

        return parent::_afterLoad($object);
    }

    /**
     * Replaces the store view labels, but only when they differ from what was loaded: the carrier
     * list refresh saves every carrier and must not write back labels read before an admin edit.
     */
    protected function _afterSave(AbstractModel $object): static
    {
        if ($object->hasData(CarrierConfigInterface::FIELD_STORE_LABELS)
            && $object->dataHasChangedFor(CarrierConfigInterface::FIELD_STORE_LABELS)
        ) {
            $connection = $this->getConnection();
            $table = $this->getTable(self::LABEL_TABLE_NAME);
            $configId = (int)$object->getId();
            $rows = [];
            foreach ((array)$object->getData(CarrierConfigInterface::FIELD_STORE_LABELS) as $label) {
                if ($label instanceof CarrierLabelInterface && \trim($label->getLabel()) !== '') {
                    $rows[$label->getStoreId()] = [
                        CarrierConfigInterface::FIELD_CONFIG_ID => $configId,
                        CarrierLabelInterface::FIELD_STORE_ID => $label->getStoreId(),
                        CarrierLabelInterface::FIELD_LABEL => \trim($label->getLabel()),
                    ];
                }
            }
            $connection->delete($table, ['config_id = ?' => $configId]);
            if ($rows !== []) {
                $connection->insertMultiple($table, \array_values($rows));
            }
            $object->setOrigData(
                CarrierConfigInterface::FIELD_STORE_LABELS,
                $object->getData(CarrierConfigInterface::FIELD_STORE_LABELS),
            );
        }

        return parent::_afterSave($object);
    }
}
