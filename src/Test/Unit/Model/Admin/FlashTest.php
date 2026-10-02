<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Admin;

use Magento\Framework\Message\ManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Admin\Flash;

#[CoversClass(Flash::class)]
class FlashTest extends TestCase
{
    /** @var array<string, string[]> */
    private array $sent = [];

    public function testEverythingAnActionSaysIsOneMessageOfItsGravestKind(): void
    {
        (new Flash())->success('10 deleted.')->warning('2 not deleted: refused.')->notice('Checked later.')->send($this->messages());

        self::assertSame(['warning' => ['10 deleted. 2 not deleted: refused. Checked later.']], $this->sent);
    }

    public function testNothingToSayIsNoMessage(): void
    {
        $flash = (new Flash())->warning('  ');

        self::assertTrue($flash->isEmpty());
        $flash->send($this->messages());
        self::assertSame([], $this->sent);
    }

    public function testItStartsOverOnceSent(): void
    {
        $flash = (new Flash())->error('Refused.');
        $flash->send($this->messages());
        $flash->success('Saved.')->send($this->messages());

        self::assertSame(['error' => ['Refused.'], 'success' => ['Saved.']], $this->sent);
    }

    private function messages(): ManagerInterface
    {
        $messages = $this->createStub(ManagerInterface::class);
        foreach (['error', 'warning', 'notice', 'success'] as $type) {
            $messages->method('add' . \ucfirst($type) . 'Message')->willReturnCallback(function ($text) use ($type, $messages) {
                $this->sent[$type][] = (string)$text;

                return $messages;
            });
        }

        return $messages;
    }
}
