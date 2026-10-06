<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Webhook;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentManagementInterface;
use Polkurier\Shipping\Model\Config;
use Polkurier\Shipping\Model\Shipment\ShipmentFinder;
use Polkurier\Shipping\Model\Webhook\StatusWebhook;
use Psr\Log\LoggerInterface;

#[CoversClass(StatusWebhook::class)]
#[AllowMockObjectsWithoutExpectations]
class StatusWebhookTest extends TestCase
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
        $webhook = $this->webhook([31]);

        self::assertSame(StatusWebhook::RECEIVED, $webhook->receive($this->body('UPDATED', '711400-27')));
        self::assertSame([31], $this->refreshed);
        self::assertSame([['711400-27'], [ShipmentInterface::STATE_CREATED]], $this->query, 'Only a placed shipment of this shop\'s, found by its number.');
    }

    public function testACancellationIsReadFromPolkurierWhateverTheEventSays(): void
    {
        // Polkurier's staff cancel with an UPDATED event; the order read again says it's cancelled.
        self::assertSame(StatusWebhook::RECEIVED, $this->webhook([31])->receive($this->body('UPDATED', '711400-27', ['status_code' => 'CANCELED'])));
        self::assertSame([31], $this->refreshed);
    }

    public function testTheSignatureCoversTheWholeBodyBeforeTheHash(): void
    {
        $body = $this->body('UPDATED', '711400-27');

        self::assertSame(StatusWebhook::REFUSED, $this->webhook([31])->receive(\str_replace('711400-27', '711400-28', $body)));
        self::assertSame(StatusWebhook::REFUSED, $this->webhook([31], 'another-secret')->receive($body));
        self::assertSame([], $this->refreshed);
    }

    public function testWithoutASecretNothingIsTakenIn(): void
    {
        self::assertSame(StatusWebhook::REFUSED, $this->webhook([31], '')->receive($this->body('UPDATED', '711400-27')));
        self::assertSame([], $this->refreshed);
    }

    public function testABodyWhoseLastFieldIsntTheHashIsRefused(): void
    {
        $body = $this->body('UPDATED', '711400-27') . '&data%5Bnumber%5D=1';

        self::assertSame(StatusWebhook::REFUSED, $this->webhook([31])->receive($body));
        self::assertSame(StatusWebhook::REFUSED, $this->webhook([31])->receive('type=UPDATED'));
    }

    public function testAnOrderThisShopDidntPlaceIsAcknowledgedAndLeftAlone(): void
    {
        self::assertSame(StatusWebhook::RECEIVED, $this->webhook([])->receive($this->body('CREATED', '711400-99')));
        self::assertSame([], $this->refreshed);
    }

    public function testAShipmentThatCouldntBeRefreshedIsReportedAsFailed(): void
    {
        self::assertSame(StatusWebhook::FAILED, $this->webhook([31], self::SECRET, true)->receive($this->body('UPDATED', '711400-27')));
    }

    /**
     * As Polkurier builds it: the HMAC of the form-encoded type and data, then the hash added last.
     *
     * @param array<string, mixed> $more more order fields
     */
    private function body(string $type, string $number, array $more = []): string
    {
        $fields = [
            'type' => $type,
            'data' => [
                'number' => $number,
                'status' => 'Oczekuje na odbiór',
                'status_code' => 'OC',
                'delivered_date' => '',
                'is_paid' => true,
                'price_gross' => 22.85,
                // Dropped by the form encoding on both sides.
                'cover_address' => null,
                'COD' => [],
                'waybills' => [['number' => '0000000000', 'is_default' => true]],
                'external_order_id' => '1042',
            ] + $more,
        ];
        $fields['hash'] = \hash_hmac('sha256', \http_build_query($fields), self::SECRET);

        return \http_build_query($fields, '', '&');
    }

    /**
     * @param int[] $shipmentIds the shipments found by the number
     */
    private function webhook(array $shipmentIds, string $secret = self::SECRET, bool $failing = false): StatusWebhook
    {
        $config = $this->createStub(Config::class);
        $config->method('getWebhookSecret')->willReturn($secret);
        $finder = $this->createStub(ShipmentFinder::class);
        $finder->method('byNumbers')->willReturnCallback(function (array $numbers, array $states) use ($shipmentIds): array {
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
                throw new LocalizedException(\__('Polkurier could not be reached.'));
            }
            $this->refreshed[] = $id;

            return $this->createStub(ShipmentInterface::class);
        });

        return new StatusWebhook($config, $finder, $management, $this->createStub(LoggerInterface::class));
    }
}
