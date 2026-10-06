<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form;

use Magento\Framework\Api\Filter;
use Magento\Ui\DataProvider\AbstractDataProvider;

/**
 * The data of one of the module's forms or pages, which reads what it shows by its own request
 * parameter, not through the collection's filters, and once per request.
 */
abstract class FormDataProvider extends AbstractDataProvider
{
    /**
     * @var array<int|string, array<string, mixed>>|null
     */
    private ?array $loaded = null;

    /**
     * Narrowed by the form's own request parameter instead.
     */
    public function addFilter(Filter $filter): void
    {
    }

    /**
     * @return array<int|string, array<string, mixed>>
     */
    public function getData(): array
    {
        return $this->loaded ??= $this->load();
    }

    /**
     * @return array<int|string, array<string, mixed>> the form's data by its record's id
     */
    abstract protected function load(): array;
}
