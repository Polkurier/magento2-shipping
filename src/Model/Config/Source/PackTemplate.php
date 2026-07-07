<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;

readonly class PackTemplate implements OptionSourceInterface
{
    /**
     * @param PackTemplateRepositoryInterface $packTemplateRepository
     */
    public function __construct(
        /**
         * @var PackTemplateRepositoryInterface
         */
        private PackTemplateRepositoryInterface $packTemplateRepository,
    ) {}

    /**
     * @return array
     */
    public function toOptionArray(): array
    {
        return \array_map(
            fn (PackTemplateInterface $template) => [
                'value' => $template->getTemplateId(),
                'label' => $template->getName()
            ],
            $this->packTemplateRepository->findAll()->getItems()
        );
    }
}
