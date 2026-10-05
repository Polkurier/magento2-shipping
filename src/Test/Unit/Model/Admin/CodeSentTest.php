<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Admin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Admin\CodeSent;
use Polkurier\Webservice\Response\TwoFactorToken;
use Polkurier\Webservice\Response\TwoFactorToken\TransportMethodMessage;

#[CoversClass(CodeSent::class)]
class CodeSentTest extends TestCase
{
    public function testItSaysWhereTheCodeWentByTheMethodItWentBy(): void
    {
        self::assertSame('Code sent to +48 *** *** 701; valid until 14:35.', CodeSent::message($this->token(new TransportMethodMessage(id: '1', name: '+48 *** *** 701'))));
    }

    public function testWithoutAMethodItSaysOnlyUntilWhen(): void
    {
        self::assertSame('Code sent; valid until 14:35.', CodeSent::message($this->token(null)));
    }

    private function token(?TransportMethodMessage $method): TwoFactorToken
    {
        $now = new \DateTimeImmutable('2026-10-05 14:30:00');

        return new TwoFactorToken('t1', '', 'Wypłata', 1, $now, $now, $now->modify('+5 minutes'), $method);
    }
}
