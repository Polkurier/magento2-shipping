<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\ResourceModel\Address;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Polkurier\Shipping\Model\Address;
use Polkurier\Shipping\Model\ResourceModel\Address as AddressResource;

class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Address::class, AddressResource::class);
    }

    /**
     * @return $this
     */
    public function addCoverFilter(): self
    {
        $this->addFieldToFilter(Address::FIELD_COVER, ['eq' => '1']);

        return $this;
    }
}
