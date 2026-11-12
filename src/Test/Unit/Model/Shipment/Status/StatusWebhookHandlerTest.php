<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Shipment\Status;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Logger\Logger;
use Polkurier\Shipping\Model\Config\ConfigProvider;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\Shipment\Status\StatusWebhookHandler;
use Polkurier\Webservice\Webhook\OrderWebhookReader;

#[CoversClass(StatusWebhookHandler::class)]
#[AllowMockObjectsWithoutExpectations]
class StatusWebhookHandlerTest extends TestCase
{
    private const string SECRET = 'panel-secret';

    /**
     * @var array{0: string[], 1: string[]}|null the numbers and states the shipments were looked up by
     */
    private ?array $query = null;

    /**
     * @var int[]
     */
    private array $refreshed = [];

    public function testAnEventSignedWithTheSecretRefreshesItsShipment(): void
    {
        $webhook = $this->createHandler([31]);

        self::assertSame(StatusWebhookHandler::RECEIVED, $webhook->receive($this->buildBody('UPDATED', '24680-27')));
        self::assertSame([31], $this->refreshed);
        self::assertSame(
            [['24680-27'], [ShipmentInterface::STATE_CREATED]],
            $this->query,
            'Only a placed shipment of this shop\'s, found by its number.',
        );
    }

    public function testACancellationIsReadFromPolkurierWhateverTheEventSays(): void
    {
        // Polkurier's staff cancel with an UPDATED event; the order read again says it's cancelled.
        self::assertSame(
            StatusWebhookHandler::RECEIVED,
            $this->createHandler([31])->receive($this->buildBody('UPDATED', '24680-27', ['status_code' => 'CANCELED'])),
        );
        self::assertSame([31], $this->refreshed);
    }

    public function testTheSignatureCoversTheWholeBody(): void
    {
        $body = $this->buildBody('UPDATED', '24680-27');

        self::assertSame(
            StatusWebhookHandler::REFUSED,
            $this->createHandler([31])->receive(\str_replace('24680-27', '24680-28', $body)),
        );
        self::assertSame(StatusWebhookHandler::REFUSED, $this->createHandler([31], 'another-secret')->receive($body));
        self::assertSame([], $this->refreshed);
    }

    public function testWithoutASecretNothingIsTakenIn(): void
    {
        self::assertSame(
            StatusWebhookHandler::REFUSED,
            $this->createHandler([31], '')->receive($this->buildBody('UPDATED', '24680-27')),
        );
        self::assertSame([], $this->refreshed);
    }

    public function testAFieldAddedToASignedEventOrAnUnsignedOneIsRefused(): void
    {
        $body = $this->buildBody('UPDATED', '24680-27') . '&data%5Bnumber%5D=1';

        self::assertSame(StatusWebhookHandler::REFUSED, $this->createHandler([31])->receive($body));
        self::assertSame(StatusWebhookHandler::REFUSED, $this->createHandler([31])->receive('type=UPDATED'));
        self::assertSame([], $this->refreshed);
    }

    public function testASignedEventThatIsntAnOrderIsAcknowledgedAndLeftAlone(): void
    {
        $fields = ['type' => 'UPDATED', 'data' => ['note' => 'not an order']];
        $fields['hash'] = \hash_hmac('sha256', \http_build_query($fields), self::SECRET);

        self::assertSame(
            StatusWebhookHandler::RECEIVED,
            $this->createHandler([31])->receive(\http_build_query($fields)),
        );
        self::assertSame([], $this->refreshed);
    }

    public function testAnOrderThisShopDidntPlaceIsAcknowledgedAndLeftAlone(): void
    {
        self::assertSame(
            StatusWebhookHandler::RECEIVED,
            $this->createHandler([])->receive($this->buildBody('CREATED', '24680-99')),
        );
        self::assertSame([], $this->refreshed);
    }

    public function testAShipmentThatCouldntBeRefreshedIsReportedAsFailed(): void
    {
        self::assertSame(
            StatusWebhookHandler::FAILED,
            $this->createHandler([31], self::SECRET, true)->receive($this->buildBody('UPDATED', '24680-27')),
        );
    }

    /**
     * As Polkurier builds it: the order as get_orders gives it (a recorded one), and the HMAC of the
     * form-encoded type and data as the hash.
     *
     * @param array<string, mixed> $more more order fields
     */
    private function buildBody(string $type, string $number, array $more = []): string
    {
        $recorded = \json_decode((string)\file_get_contents(__DIR__ . '/../../../_files/get_orders.json'), true);
        $fields = ['type' => $type, 'data' => ['number' => $number] + $more + $recorded['response']['result'][0]];
        $fields['hash'] = \hash_hmac('sha256', \http_build_query($fields), self::SECRET);

        return \http_build_query($fields, '', '&');
    }

    /**
     * @param int[] $shipmentIds the shipments found by the number
     */
    private function createHandler(
        array $shipmentIds,
        string $secret = self::SECRET,
        bool $failing = false,
    ): StatusWebhookHandler {
        $config = $this->createStub(ConfigProvider::class);
        $config->method('getWebhookSecret')->willReturn($secret);
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('findByNumbers')
            ->willReturnCallback(function (array $numbers, array $states) use ($shipmentIds): array {
                $this->query = [$numbers, $states];

                return \array_map(function (int $id): ShipmentInterface {
                    $shipment = $this->createStub(ShipmentInterface::class);
                    $shipment->method('getId')->willReturn($id);

                    return $shipment;
                }, $shipmentIds);
            });
        $management = $this->createStub(ShipmentManagementInterface::class);
        $management->method('refreshStatus')->willReturnCallback(function (int $id) use ($failing): ShipmentInterface {
            if ($failing) {
                throw new LocalizedException(__('Polkurier could not be reached.'));
            }
            $this->refreshed[] = $id;

            return $this->createStub(ShipmentInterface::class);
        });

        return new StatusWebhookHandler(
            $config,
            $finder,
            $management,
            new OrderWebhookReader(),
            $this->createStub(Logger::class),
        );
    }
}
