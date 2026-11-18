<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component\Form\ShipmentView;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Model\Config\Source\LabelFormat;
use Polkurier\Shipping\Model\Shipment;
use Polkurier\Shipping\Model\Shipment\Screen\RetriableShipmentFinder;
use Polkurier\Shipping\Ui\Component\Control\OnClickScriptBuilder;
use Polkurier\Shipping\Ui\Component\Form\ShipmentView\AbstractButton;
use Polkurier\Shipping\Ui\Component\Form\ShipmentView\CancelButton;
use Polkurier\Shipping\Ui\Component\Form\ShipmentView\DiscardButton;
use Polkurier\Shipping\Ui\Component\Form\ShipmentView\LabelButton;
use Polkurier\Shipping\Ui\Component\Form\ShipmentView\LinkButton;
use Polkurier\Shipping\Ui\Component\Form\ShipmentView\ProtocolButton;
use Polkurier\Shipping\Ui\Component\Form\ShipmentView\SendAgainButton;
use Polkurier\Shipping\Ui\Component\Form\ShipmentView\ShipButton;

/**
 * The action buttons of the shipment details page, by the shipment's state and the admin's
 * permissions: the same actions the shipments list offers for the shipment
 * ({@see \Polkurier\Shipping\Test\Unit\Ui\Component\Listing\Shipment\ActionsColumnTest}), and
 * linking one without an answer to its Polkurier order.
 */
#[CoversClass(AbstractButton::class)]
#[CoversClass(CancelButton::class)]
#[CoversClass(DiscardButton::class)]
#[CoversClass(LabelButton::class)]
#[CoversClass(LinkButton::class)]
#[CoversClass(ProtocolButton::class)]
#[CoversClass(SendAgainButton::class)]
#[CoversClass(ShipButton::class)]
#[AllowMockObjectsWithoutExpectations]
class ActionButtonsTest extends TestCase
{
    /**
     * @var string[] the ACL resources allowed
     */
    private array $allowed = ['Magento_Sales::ship', 'Polkurier_Shipping::shipment_cancel'];

    public function testAPlacedOrderPrintsAndCancels(): void
    {
        $buttons = $this->buildButtons($this->createShipment(ShipmentInterface::STATE_CREATED, shipmentId: 11));

        self::assertSame(['cancel', 'protocol', 'label'], \array_keys($buttons));
        self::assertStringContainsString('polkurier\/shipment\/cancel id=5', (string)$buttons['cancel']['on_click']);
    }

    public function testAParcelTheCarrierHasIsntOfferedForCancelling(): void
    {
        $shipment = $this->createShipment(ShipmentInterface::STATE_CREATED, shipmentId: 11, statusCode: 'WP');

        self::assertSame(['protocol', 'label'], \array_keys($this->buildButtons($shipment)));
    }

    public function testAPlacedOrderWithoutItsMagentoShipmentCanCreateIt(): void
    {
        self::assertSame(
            ['cancel', 'ship', 'protocol', 'label'],
            \array_keys($this->buildButtons($this->createShipment(ShipmentInterface::STATE_CREATED))),
        );
    }

    public function testWithoutThePermissionsOnlyPrintingIsOffered(): void
    {
        $this->allowed = [];

        self::assertSame(
            ['protocol', 'label'],
            \array_keys($this->buildButtons($this->createShipment(ShipmentInterface::STATE_CREATED))),
        );
        self::assertSame([], $this->buildButtons($this->createShipment(ShipmentInterface::STATE_FAILED)));
        self::assertSame([], $this->buildButtons($this->createShipment(ShipmentInterface::STATE_UNKNOWN, 'ext-1')));
    }

    public function testOneNotPlacedIsSentAgainOrDiscarded(): void
    {
        $buttons = $this->buildButtons($this->createShipment(ShipmentInterface::STATE_FAILED));

        self::assertSame(['discard', 'send_again'], \array_keys($buttons));
        self::assertStringContainsString(
            'polkurier\/shipment\/create order_id=29 retry=5',
            (string)$buttons['send_again']['on_click'],
        );
    }

    public function testOneNotPlacedThatCantBeSentAgainCanStillBeDiscarded(): void
    {
        self::assertSame(
            ['discard'],
            \array_keys($this->buildButtons($this->createShipment(ShipmentInterface::STATE_FAILED), false)),
        );
    }

    public function testOneWithoutAnAnswerIsLinkedOnlyWhenItWasSentWithTheShopsIds(): void
    {
        self::assertSame(
            ['link'],
            \array_keys($this->buildButtons($this->createShipment(ShipmentInterface::STATE_UNKNOWN, 'ext-1'))),
        );
        self::assertSame([], $this->buildButtons($this->createShipment(ShipmentInterface::STATE_UNKNOWN)));
        self::assertSame([], $this->buildButtons($this->createShipment(ShipmentInterface::STATE_PENDING)));
    }

    public function testACancelledOneIsOnlyLookedAt(): void
    {
        self::assertSame([], $this->buildButtons($this->createShipment(ShipmentInterface::STATE_CANCELLED)));
    }

    /**
     * @return array<string, array<string, mixed>> the buttons shown, in the order of the page's
     *     buttons
     */
    private function buildButtons(ShipmentInterface $shipment, bool $canSendAgain = true): array
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnMap([['id', null, '5']]);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static function (string $path, array $params = []): string {
            $query = [];
            foreach ($params as $name => $value) {
                $query[] = $name . '=' . $value;
            }

            return \trim($path . ' ' . \implode(' ', $query));
        });
        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')
            ->willReturnCallback(fn(string $resource): bool => \in_array($resource, $this->allowed, true));
        $shipments = $this->createStub(ShipmentRepositoryInterface::class);
        $shipments->method('get')->willReturn($shipment);
        $onClick = new OnClickScriptBuilder(new Json());
        $sendAgain = $this->createStub(RetriableShipmentFinder::class);
        $sendAgain->method('isPossible')->willReturnCallback(
            static fn(ShipmentInterface $shipment): bool => $canSendAgain
                && $shipment->getState() === ShipmentInterface::STATE_FAILED,
        );
        $formats = $this->createStub(LabelFormat::class);
        $formats->method('toArray')->willReturn(['' => 'PDF']);
        $common = [$request, $url, $authorization, $shipments, $onClick];

        $buttons = [
            'cancel' => new CancelButton(...$common),
            'discard' => new DiscardButton(...$common),
            'link' => new LinkButton(...$common),
            'send_again' => new SendAgainButton(...$common, retriableShipmentFinder: $sendAgain),
            'ship' => new ShipButton(...$common),
            'protocol' => new ProtocolButton(...$common),
            'label' => new LabelButton(...$common, labelFormat: $formats),
        ];

        return \array_filter(\array_map(
            static fn(AbstractButton $button): array => $button->getButtonData(),
            $buttons,
        ));
    }

    private function createShipment(
        string $state,
        ?string $externalOrderId = null,
        ?int $shipmentId = null,
        ?string $statusCode = null,
    ): Shipment {
        /** @var Shipment $shipment */
        $shipment = (new ObjectManager($this))->getObject(Shipment::class, ['json' => new Json()]);

        $shipment->setData([
            ShipmentInterface::FIELD_ORDER_ID => 29,
            ShipmentInterface::FIELD_STATE => $state,
            ShipmentInterface::FIELD_SHIPMENT_ID => $shipmentId,
            ShipmentInterface::FIELD_POLKURIER_NUMBER => '24680-27',
            ShipmentInterface::FIELD_CARRIER_CODE => 'DPD',
            ShipmentInterface::FIELD_STATUS_CODE => $statusCode,
            ShipmentInterface::FIELD_EXTERNAL_ORDER_ID => $externalOrderId,
        ]);

        return $shipment->setId(5);
    }
}
