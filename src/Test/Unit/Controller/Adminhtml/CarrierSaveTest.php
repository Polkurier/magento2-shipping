<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use Polkurier\Shipping\Api\CarrierConfigRepositoryInterface;
use Polkurier\Shipping\Api\Data\CarrierConfigInterface;
use Polkurier\Shipping\Controller\Adminhtml\Carrier\Save;
use Polkurier\Shipping\Enum\Adminhtml\FormMemoryEnum;
use Polkurier\Shipping\Model\Admin\FormActions;
use Polkurier\Shipping\Model\CarrierConfig\CarrierConfigHydrator;
use Polkurier\Shipping\Model\PickupPoint\DefaultSenderPointManager;
use Polkurier\Shipping\Test\Unit\Support\AdminActionTestCase;

#[CoversClass(Save::class)]
#[AllowMockObjectsWithoutExpectations]
class CarrierSaveTest extends AdminActionTestCase
{
    /**
     * @var string[]
     */
    private array $happened = [];

    /**
     * @var array<string, mixed>|null what the form gets back
     */
    private ?array $kept = null;

    public function testTheSettingsAreSavedAndTheListOpens(): void
    {
        $this->post = ['id' => '7', 'form_key' => 'k', 'pricing_model' => 'FIXED', 'price' => '12.99'];

        $this->save()->execute();

        self::assertSame(['get 7', 'hydrate FIXED', 'save', 'clear'], $this->happened);
        self::assertSame(['Saved.'], $this->messages['success']);
        self::assertSame(['*/*/index', []], $this->redirect);
    }

    public function testSaveAndContinueEditComesBackToTheForm(): void
    {
        $this->post = ['id' => '7', 'pricing_model' => 'FIXED'];
        $this->params = [FormActions::BACK_PARAM => FormActions::SAVE_AND_CONTINUE];

        $this->save()->execute();

        self::assertSame(['*/*/edit', ['id' => 7]], $this->redirect);
    }

    public function testWhatCantBeSavedGoesBackToTheFormWithWhatWasTyped(): void
    {
        $this->post = ['id' => '7', 'form_key' => 'k', 'pricing_model' => 'TIER_WEIGHT', 'tiers' => []];

        $this->save(new LocalizedException(__('Add at least one price tier.')))->execute();

        self::assertSame(['Add at least one price tier.'], $this->messages['error']);
        self::assertSame(
            ['id' => '7', 'pricing_model' => 'TIER_WEIGHT', 'tiers' => []],
            $this->kept,
            'Without the form key.',
        );
        self::assertSame(['*/*/edit', ['id' => 7]], $this->redirect);
        self::assertNotContains('save', $this->happened);
    }

    public function testAChangedDropOffPointGoesToPolkurierBeforeTheCarrierIsSaved(): void
    {
        $this->post = [
            'id' => '7',
            'pricing_model' => 'FIXED',
            'default_sender_point' => ' U02842068 ',
            'default_sender_point_before' => 'U01000001',
        ];

        $this->save()->execute();

        self::assertSame(
            ['get 7', 'hydrate FIXED', 'point UPS_AP_POINT_POINT U02842068', 'save', 'clear'],
            $this->happened,
        );
    }

    public function testAnUnchangedDropOffPointIsLeftAlone(): void
    {
        $this->post = [
            'id' => '7',
            'pricing_model' => 'FIXED',
            'default_sender_point' => 'U02842068',
            'default_sender_point_before' => 'U02842068',
        ];

        $this->save()->execute();

        self::assertSame(['get 7', 'hydrate FIXED', 'save', 'clear'], $this->happened);
    }

    public function testADropOffPointPolkurierRefusesSavesNothing(): void
    {
        $this->post = [
            'id' => '7',
            'pricing_model' => 'FIXED',
            'default_sender_point' => 'PL63197',
            'default_sender_point_before' => '',
        ];

        $this->save(pointRefusal: new LocalizedException(__('Punkt PL63197 jest nieprawidłowy')))->execute();

        self::assertSame(['Punkt PL63197 jest nieprawidłowy'], $this->messages['error']);
        self::assertSame(['get 7', 'hydrate FIXED', 'point UPS_AP_POINT_POINT PL63197'], $this->happened);
        self::assertSame('PL63197', $this->kept['default_sender_point'] ?? null, 'Back to the form with it.');
    }

    public function testPricesThatCantBeSavedKeepTheDropOffPointFromPolkurier(): void
    {
        $this->post = [
            'id' => '7',
            'pricing_model' => 'FIXED',
            'default_sender_point' => 'U02842068',
            'default_sender_point_before' => '',
        ];

        $this->save(new LocalizedException(__('Enter the price.')))->execute();

        self::assertSame(['Enter the price.'], $this->messages['error']);
        self::assertSame(['get 7', 'hydrate FIXED'], $this->happened);
    }

    public function testACarrierThatsGoneIsSaid(): void
    {
        $this->post = ['id' => '99'];

        $this->save(null, false)->execute();

        self::assertSame(['The carrier settings with id "99" do not exist.'], $this->messages['error']);
    }

    private function save(
        ?LocalizedException $refusal = null,
        bool $exists = true,
        ?LocalizedException $pointRefusal = null,
    ): Save {
        $config = $this->createStub(CarrierConfigInterface::class);
        $config->method('getCarrierCode')->willReturn('UPS_AP_POINT_POINT');
        $repository = $this->createStub(CarrierConfigRepositoryInterface::class);
        $repository->method('get')
            ->willReturnCallback(function (int $id) use ($config, $exists): CarrierConfigInterface {
                $this->happened[] = 'get ' . $id;
                if (!$exists) {
                    throw new NoSuchEntityException(__('The carrier settings with id "%1" do not exist.', $id));
                }

                return $config;
            });
        $repository->method('save')
            ->willReturnCallback(function (CarrierConfigInterface $config): CarrierConfigInterface {
                $this->happened[] = 'save';

                return $config;
            });
        $hydrator = $this->createStub(CarrierConfigHydrator::class);
        $hydrator->method('hydrate')
            ->willReturnCallback(function (CarrierConfigInterface $config, array $data) use ($refusal): void {
                $this->happened[] = 'hydrate ' . $data['pricing_model'];
                if ($refusal !== null) {
                    throw $refusal;
                }
            });
        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('set')->willReturnCallback(function (string $key, mixed $value): void {
            self::assertSame(FormMemoryEnum::Carrier->value, $key);
            $this->kept = $value;
        });
        $persistor->method('clear')->willReturnCallback(function (): void {
            $this->happened[] = 'clear';
        });

        $points = $this->createStub(DefaultSenderPointManager::class);
        $points->method('change')
            ->willReturnCallback(function (string $carrierCode, string $pointId) use ($pointRefusal): void {
                $this->happened[] = 'point ' . $carrierCode . ' ' . $pointId;
                if ($pointRefusal !== null) {
                    throw $pointRefusal;
                }
            });

        return new Save($this->createContext(), $repository, $hydrator, $persistor, $points);
    }
}
