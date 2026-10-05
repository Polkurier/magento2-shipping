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
 * Edit and delete links of the settings grids, to the paths the listing gives in its config:
 * editUrlPath, and deleteUrlPath where entries can be deleted, confirmed with deleteMessage.
 */
class EditActionsColumn extends Column
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
        $index = (string)$this->getData('config/indexField');
        $editPath = (string)$this->getData('config/editUrlPath');
        $deletePath = (string)$this->getData('config/deleteUrlPath');
        $deleteMessage = (string)($this->getData('config/deleteMessage') ?: \__('Delete this entry?'));

        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int)$item[$index];
            $item[$this->getData('name')] = ['edit' => [
                'href' => $this->url->getUrl($editPath, ['id' => $id]),
                'label' => \__('Edit'),
            ]];
            if ($deletePath !== '') {
                $item[$this->getData('name')]['delete'] = [
                    'href' => $this->url->getUrl($deletePath, ['id' => $id]),
                    'label' => \__('Delete'),
                    'post' => true,
                    'confirm' => ['title' => \__('Delete'), 'message' => $deleteMessage],
                ];
            }
        }

        return $dataSource;
    }
}
