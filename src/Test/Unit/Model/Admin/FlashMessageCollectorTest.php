<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Admin;

use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Admin\FlashMessageCollector;
use Polkurier\Webservice\Response\TwoFactorToken;
use Polkurier\Webservice\Response\TwoFactorToken\TransportMethodMessage;

#[CoversClass(FlashMessageCollector::class)]
class FlashMessageCollectorTest extends TestCase
{
    /** @var array<string, string[]> */
    private array $sent = [];

    public function testEverythingAnActionSaysIsOneMessageOfItsGravestKind(): void
    {
        (new FlashMessageCollector())
            ->addSuccess('10 deleted.')
            ->addWarning('2 not deleted: refused.')
            ->addNotice('Checked later.')
            ->send($this->createMessageManager());

        self::assertSame(['warning' => ['10 deleted. 2 not deleted: refused. Checked later.']], $this->sent);
    }

    public function testNothingToSayIsNoMessage(): void
    {
        (new FlashMessageCollector())->addWarning('  ')->send($this->createMessageManager());

        self::assertSame([], $this->sent);
    }

    public function testItStartsOverOnceSent(): void
    {
        $flash = (new FlashMessageCollector())->addError('Refused.');
        $flash->send($this->createMessageManager());
        $flash->addSuccess('Saved.')->send($this->createMessageManager());

        self::assertSame(['error' => ['Refused.'], 'success' => ['Saved.']], $this->sent);
    }

    public function testItSaysWhereTheCodeWentByTheMethodItWentBy(): void
    {
        self::assertSame(
            'Code sent to +48 *** *** 701; valid until 14:35.',
            FlashMessageCollector::formatCodeSent(
                $this->createToken(new TransportMethodMessage(id: '1', name: '+48 *** *** 701')),
            ),
        );
    }

    public function testWithoutAMethodItSaysOnlyUntilWhen(): void
    {
        self::assertSame(
            'Code sent; valid until 14:35.',
            FlashMessageCollector::formatCodeSent($this->createToken(null)),
        );
    }

    private function createMessageManager(): ManagerInterface
    {
        $messages = $this->createStub(ManagerInterface::class);
        foreach (['error', 'warning', 'notice', 'success'] as $type) {
            $messages->method('add' . \ucfirst($type) . 'Message')
                ->willReturnCallback(function ($text) use ($type, $messages) {
                    $this->sent[$type][] = (string)$text;

                    return $messages;
                });
        }

        return $messages;
    }

    private function createToken(?TransportMethodMessage $method): TwoFactorToken
    {
        $now = new \DateTimeImmutable('2026-10-05 14:30:00');

        return new TwoFactorToken('t1', '', 'Wypłata', 1, $now, $now, $now->modify('+5 minutes'), $method);
    }
}
