<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Polkurier\Webservice\Api\MessageInterface;
use Polkurier\Webservice\Api\Validation\ValidatorInterface;
use Polkurier\Webservice\Validator\DefaultValidatorRegistryFactory;

/**
 * The SDK's default payload validation, in a shape the object manager can construct.
 */
class Validator implements ValidatorInterface
{
    private ?ValidatorInterface $inner = null;

    public function validate(MessageInterface $data): void
    {
        $this->inner ??= DefaultValidatorRegistryFactory::create();
        $this->inner->validate($data);
    }
}
