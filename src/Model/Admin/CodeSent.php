<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Admin;

use Polkurier\Webservice\Response\TwoFactorToken;

/**
 * Says where a confirmation code went and until when it's valid: where, by the name of the method
 * Polkurier sent it by (a partly masked phone number or e-mail address).
 */
final class CodeSent
{
    public static function message(TwoFactorToken $token): string
    {
        $where = \trim((string)$token->getTransportMethod()?->getName()) ?: \trim($token->getPhoneNumber());
        $until = $token->getExpiresAt()->format('H:i');

        return $where !== ''
            ? (string)\__('Code sent to %1; valid until %2.', $where, $until)
            : (string)\__('Code sent; valid until %1.', $until);
    }
}
