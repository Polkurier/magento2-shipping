<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment;

use Magento\Framework\App\Request\DataPersistorInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Shipment\RejectedDraft;

#[CoversClass(RejectedDraft::class)]
#[AllowMockObjectsWithoutExpectations]
class RejectedDraftTest extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    private array $session = [];

    public function testTheFormIsHandedBackOnceForItsOrder(): void
    {
        $draft = $this->draft();
        $draft->keep(['order_id' => 7, 'recipient_phone' => '600700800']);

        self::assertNull($draft->take(8), 'Another order opens with its defaults.');
        self::assertSame(['order_id' => 7, 'recipient_phone' => '600700800'], $draft->take(7));
        self::assertNull($draft->take(7));
    }

    private function draft(): RejectedDraft
    {
        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('set')->willReturnCallback(function (string $key, mixed $data): void {
            $this->session[$key] = $data;
        });
        $persistor->method('get')->willReturnCallback(fn(string $key): mixed => $this->session[$key] ?? null);
        $persistor->method('clear')->willReturnCallback(function (string $key): void {
            unset($this->session[$key]);
        });

        return new RejectedDraft($persistor);
    }
}
