<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel;

use Magento\Framework\Model\AbstractModel;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuotePoint extends AbstractDb
{
    public const string TABLE_NAME = 'polkurier_quote_point';

    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, 'entity_id');
    }

    /**
     * Loads the point chosen for one shipping address of a quote; 0 is the regular checkout's.
     */
    public function loadForQuote(AbstractModel $point, int $quoteId, int $quoteAddressId = 0): void
    {
        $connection = $this->getConnection();
        $id = $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), ['entity_id'])
                ->where('quote_id = ?', $quoteId)
                ->where('quote_address_id = ?', $quoteAddressId),
        );
        if ($id !== false) {
            $this->load($point, (int)$id);
        }
    }
}
