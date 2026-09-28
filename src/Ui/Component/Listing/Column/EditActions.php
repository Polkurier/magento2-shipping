<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Edit and delete links of the settings grids. The controller path follows from the listing:
 * polkurier_pack_template_listing edits through polkurier/packtemplate/edit.
 */
class EditActions extends Column
{
    /**
     * @param array<string, mixed> $components
     * @param array<string, mixed> $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $url,
        array $components = [],
        array $data = [],
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * @param array<string, mixed> $dataSource
     * @return array<string, mixed>
     */
    public function prepareDataSource(array $dataSource): array
    {
        $namespace = (string)$this->getContext()->getNamespace();
        $controller = \str_replace('_', '', \preg_replace('/^polkurier_(.*)_listing$/', '$1', $namespace) ?? '');
        $index = (string)$this->getData('config/indexField');
        $deletable = $controller !== 'carrier';

        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int)$item[$index];
            $item[$this->getData('name')] = ['edit' => [
                'href' => $this->url->getUrl("polkurier/{$controller}/edit", ['id' => $id]),
                'label' => \__('Edit'),
            ]];
            if ($deletable) {
                $item[$this->getData('name')]['delete'] = [
                    'href' => $this->url->getUrl("polkurier/{$controller}/delete", ['id' => $id]),
                    'label' => \__('Delete'),
                    'post' => true,
                    'confirm' => ['title' => \__('Delete'), 'message' => \__('Delete this entry?')],
                ];
            }
        }

        return $dataSource;
    }
}
