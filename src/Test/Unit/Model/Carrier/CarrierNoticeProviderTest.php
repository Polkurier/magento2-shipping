<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Carrier;

use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Translate\InlineInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\PolkurierClientInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Carrier\CarrierNoticeProvider;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Webservice\Api\Service\SystemServiceInterface;
use Polkurier\Webservice\Response\System\CourierMessage;

#[CoversClass(CarrierNoticeProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class CarrierNoticeProviderTest extends TestCase
{
    public function testLinksInANoticeOpenInANewTabAndOtherMarkupIsDropped(): void
    {
        $notices = $this->provider(new CourierMessage(
            'INPOST',
            'Więcej informacji znajdziesz <a href="https://polkurier.pl/cennik">TUTAJ</a><script>x()</script>',
        ))->getNotices();

        self::assertSame(
            'Więcej informacji znajdziesz '
            . '<a target="_blank" rel="noopener" href="https://polkurier.pl/cennik">TUTAJ</a>x()',
            $notices[0]['html'],
        );
    }

    private function provider(CourierMessage $message): CarrierNoticeProvider
    {
        $system = $this->createStub(SystemServiceInterface::class);
        $system->method('getCouriersMessages')->willReturn([$message]);
        $client = $this->createStub(PolkurierClientInterface::class);
        $client->method('system')->willReturn($system);
        $escaper = new Escaper();
        // Its inline translator would come from the object manager.
        (new \ReflectionProperty(Escaper::class, 'translateInline'))
            ->setValue($escaper, $this->createStub(InlineInterface::class));

        return new CarrierNoticeProvider(
            $client,
            $this->createStub(PolkurierCache::class),
            $escaper,
            $this->createStub(Logger::class),
            new Json(),
            $this->createStub(AccountCache::class),
        );
    }
}
