<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Webservice\Exception\ConnectionException;

#[CoversClass(ErrorMessages::class)]
class ErrorMessagesTest extends TestCase
{
    public function testTheExceptionCarriesTheDescribedMessageAsItIs(): void
    {
        $failure = new ConnectionException('Timed out.');

        $exception = (new ErrorMessages())->exception($failure);

        self::assertSame('Polkurier could not be reached, or did not answer in time: Timed out.', $exception->getMessage());
        self::assertSame('Polkurier could not be reached, or did not answer in time: Timed out.', $exception->getRawMessage(), 'No "%1" placeholder to translate.');
        self::assertSame($failure, $exception->getPrevious());
    }
}
