<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Api\Data;

use Polkurier\Webservice\Api\Data\Entity\PackTemplateInterface as BasePackTemplateInterface;

interface PackTemplateInterface extends BasePackTemplateInterface
{
    /**
     * @return int|null
     */
    public function getTemplateId(): ?int;

    /**
     * @param int|null $templateId
     *
     * @return void
     */
    public function setTemplateId(?int $templateId): void;
}
