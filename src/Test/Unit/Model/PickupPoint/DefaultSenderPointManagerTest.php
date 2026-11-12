<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\PickupPoint;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Cache\Type as PolkurierCache;
use Polkurier\Shipping\Model\Carrier\CarrierFeatureResolver;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\PickupPoint\DefaultSenderPointManager;
use Polkurier\Shipping\Model\Sdk\AccountCache;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Shipping\Test\Unit\Support\SdkTestCase;
use Polkurier\Webservice\Enum\Courier\PointRequirementEnum;
use Polkurier\Webservice\Response\Carrier;
use Polkurier\Webservice\Response\Carrier\AdditionalData;
use Polkurier\Webservice\Response\Carrier\AdditionalData\PointsMap;
use Polkurier\Webservice\Testing\RecordingClient;

#[CoversClass(DefaultSenderPointManager::class)]
#[AllowMockObjectsWithoutExpectations]
class DefaultSenderPointManagerTest extends SdkTestCase
{
    /**
     * @var array<string, string>
     */
    private array $cached = [];

    /**
     * @var array<string, int>
     */
    private array $lifetimes = [];

    public function testEveryCarrierPolkurierKeepsADefaultForIsReadOncePerRequest(): void
    {
        $http = new RecordingClient(self::success([
            'default_sender_point' => [
                'INPOST_PACZKOMAT' => 'KRA01M',
                'DPD_POINT' => null,
                'UPS_AP_POINT_POINT' => ' ',
            ],
        ]));
        $manager = $this->createManager($http);

        $first = $manager->get();
        $second = $manager->get();

        $points = ['INPOST_PACZKOMAT' => 'KRA01M', 'DPD_POINT' => '', 'UPS_AP_POINT_POINT' => ''];
        self::assertSame([$points, $points], [$first, $second]);
        self::assertSame(1, $http->requestCount());
        self::assertSame('get_account_info', $http->lastPayload()['apimethod']);

        $manager->_resetState();
        $manager->get();
        self::assertSame(2, $http->requestCount(), 'The next request reads them again.');
    }

    public function testACarrierHasADefaultOnlyWhenItTakesParcelsAtPointsAndPolkurierKeepsOne(): void
    {
        $manager = $this->createManager(new RecordingClient(self::success([
            'default_sender_point' => [
                'INPOST_PACZKOMAT' => 'KRA01M',
                'DPD_POINT' => null,
                'FEDEX_DTR' => 'FDX01',
            ],
        ])));

        self::assertSame(
            ['KRA01M', '', null, null],
            [
                $manager->getForCarrier(self::createCarrier('INPOST_PACZKOMAT', PointRequirementEnum::Optional)),
                $manager->getForCarrier(self::createCarrier('DPD_POINT', PointRequirementEnum::Required)),
                $manager->getForCarrier(self::createCarrier('DPD_POINT_COURIER', PointRequirementEnum::Required)),
                $manager->getForCarrier(self::createCarrier('FEDEX_DTR', PointRequirementEnum::None)),
            ],
            'Set, not set, not kept by Polkurier, and a carrier that takes no parcels at points.',
        );
    }

    public function testWhenPolkurierCantBeAskedThereAreNoneForAMoment(): void
    {
        $http = new RecordingClient('<html>Bad gateway</html>');
        $logger = $this->createMock(Logger::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Default drop-off points unavailable', self::arrayHasKey('exception'));

        self::assertSame([], $this->createManager($http, $logger)->get());
        self::assertSame([], $this->createManager($http)->get(), 'The next request is told without asking again.');
        self::assertSame(1, $http->requestCount());
        self::assertSame([PolkurierCache::FAILURE_LIFETIME], \array_values($this->lifetimes));
    }

    public function testAChangedPointIsSentAndReadAgainAfterwards(): void
    {
        $http = new RecordingClient(self::success(['default_sender_point' => ['UPS_AP_POINT_POINT' => null]]));
        $manager = $this->createManager($http);
        $manager->get();
        $http->willAnswerWith(self::success(true));

        $manager->change('UPS_AP_POINT_POINT', 'U02842068');

        self::assertSame('change_default_sender_point', $http->lastPayload()['apimethod']);
        self::assertSame(['courier' => 'UPS_AP_POINT_POINT', 'point' => 'U02842068'], $http->lastPayload()['data']);
        $http->willAnswerWith(self::success(['default_sender_point' => ['UPS_AP_POINT_POINT' => 'U02842068']]));
        self::assertSame(['UPS_AP_POINT_POINT' => 'U02842068'], $manager->get(), 'Read again after the change.');
    }

    public function testARefusalIsToldInPolkuriersWords(): void
    {
        $http = new RecordingClient((string)\json_encode([
            'status' => 'error',
            'status_code' => 24,
            'error_code' => 'PARAMETER_INVALID',
            'response' => 'Punkt PL63197 jest nieprawidłowy',
        ]));

        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessageMatches('/Punkt PL63197 jest nieprawidłowy/');
        $this->createManager($http)->change('UPS_AP_POINT_POINT', 'PL63197');
    }

    private static function createCarrier(string $code, PointRequirementEnum $senderPoint): Carrier
    {
        return new Carrier($code, $code, false, new AdditionalData(
            pointsMap: new PointsMap(['provider'], senderPoint: $senderPoint),
        ));
    }

    private function createManager(RecordingClient $http, ?Logger $logger = null): DefaultSenderPointManager
    {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getLogin')->willReturn(self::LOGIN);
        $cache = $this->createStub(PolkurierCache::class);
        $cache->method('load')->willReturnCallback(fn(string $key): string|false => $this->cached[$key] ?? false);
        $cache->method('save')
            ->willReturnCallback(function (string $data, string $key, array $tags, int $lifetime): bool {
                $this->cached[$key] = $data;
                $this->lifetimes[$key] = $lifetime;

                return true;
            });

        return new DefaultSenderPointManager(
            $this->clientPool($http),
            new CarrierFeatureResolver(),
            $cache,
            new AccountCache($this->createStub(PolkurierCache::class), new Json(), $config),
            new ErrorMessageResolver(),
            $logger ?? $this->createStub(Logger::class),
        );
    }
}
