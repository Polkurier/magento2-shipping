<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\App\RequestInterface;

/**
 * A request parameter read as text. A parameter sent as a list (name[]=…) reads as empty, rather
 * than as "Array" with a PHP warning, which Magento turns into an error page.
 */
final class RequestParam
{
    public static function string(RequestInterface $request, string $name, string $default = ''): string
    {
        $value = $request->getParam($name, $default);

        return \is_scalar($value) ? (string)$value : '';
    }
}
