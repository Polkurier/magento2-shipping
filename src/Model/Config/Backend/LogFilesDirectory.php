<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Polkurier\Shipping\Model\Sdk\LogFilesDirectoryResolver as Directory;

/**
 * Refuses a directory for logged files that the web server serves.
 */
class LogFilesDirectory extends Value
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        private readonly Directory $directory,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = [],
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
    }

    /**
     * @throws LocalizedException
     */
    public function beforeSave(): self
    {
        $entered = \trim((string)$this->getValue());
        $this->setValue($entered);
        if ($this->directory->isPublic($this->directory->resolve($entered))) {
            throw new LocalizedException(__(
                'Logged files can\'t be saved in the pub directory: anyone could download them. '
                . 'Choose a directory outside it.',
            ));
        }
        parent::beforeSave();

        return $this;
    }
}
