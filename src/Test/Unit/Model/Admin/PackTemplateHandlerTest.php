<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Admin;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\Data\PackTemplateInterface;
use Polkurier\Shipping\Api\Data\SyncableInterface;
use Polkurier\Shipping\Api\PackTemplateRepositoryInterface;
use Polkurier\Shipping\Api\SyncEntityEnum;
use Polkurier\Shipping\Api\SynchronizationInterface;
use Polkurier\Shipping\Model\Admin\PackTemplateHandler;
use Polkurier\Shipping\Model\Admin\SyncableEntityHandler;
use Polkurier\Shipping\Model\PackTemplate;
use Polkurier\Shipping\Model\PackTemplateFactory;
use Polkurier\Shipping\Model\Sync\SyncLock;
use Polkurier\Shipping\Test\Unit\Support\FixedDateTime;

#[CoversClass(PackTemplateHandler::class)]
#[CoversClass(SyncableEntityHandler::class)]
#[AllowMockObjectsWithoutExpectations]
class PackTemplateHandlerTest extends TestCase
{
    /**
     * @var string[] what happened, in order
     */
    private array $log = [];
    private bool $syncRunning = false;

    public function testASaveKeepsTheFormNotTheSyncFieldsAndSendsThatEntryAlone(): void
    {
        $template = $this->template()->setExtId('remote-7')->setSyncState(SyncableInterface::STATE_SYNCED);

        $report = $this->handler()->save($template, [
            'name' => 'Karton L',
            'length' => '60',
            'ext_id' => 'forged',
            'sync_state' => SyncableInterface::STATE_SYNCED,
            'synced_at' => '2020-01-01 00:00:00',
        ]);

        self::assertSame('Karton L', $template->getName());
        self::assertSame(60.0, (float)$template->getLength());
        self::assertSame('remote-7', $template->getExtId(), 'The link to Polkurier is the synchronization\'s.');
        self::assertSame(SyncableInterface::STATE_PENDING, $template->getSyncState());
        self::assertSame('2026-09-30 10:00:00', $template->getUpdatedAt());
        self::assertSame(['lock polkurier_sync', 'save Karton L', 'push pack_template Karton L', 'unlock polkurier_sync'], $this->log);
        self::assertSame(['Pack templates: 1 sent to Polkurier.'], $report);
    }

    public function testAnEntryNeverSentIsDeletedInMagentoAlone(): void
    {
        $this->handler()->delete($this->template()->setName('Draft'));

        self::assertSame(['lock polkurier_sync', 'delete Draft', 'unlock polkurier_sync'], $this->log);
    }

    public function testAnEntryInPolkurierIsMarkedDeletedForTheSynchronizationToCarryOut(): void
    {
        $template = $this->template()->setName('Karton M')->setExtId('remote-7');

        $this->handler()->delete($template);

        self::assertSame('2026-09-30 10:00:00', $template->getDeletedAt());
        self::assertSame(SyncableInterface::STATE_PENDING, $template->getSyncState());
        self::assertSame(['lock polkurier_sync', 'save Karton M', 'push pack_template Karton M', 'unlock polkurier_sync'], $this->log);
    }

    public function testNothingChangesWhileTheWebsiteIsSynchronizing(): void
    {
        $this->syncRunning = true;
        $template = $this->template()->setName('Karton M');

        try {
            $this->handler()->save($template, ['name' => 'Karton XL']);
            self::fail('The change waits for the synchronization.');
        } catch (LocalizedException $e) {
            self::assertStringContainsString('already running', $e->getMessage());
        }
        self::assertSame('Karton M', $template->getName());
        self::assertSame([], $this->log);
    }

    private function handler(): PackTemplateHandler
    {
        $repository = $this->createStub(PackTemplateRepositoryInterface::class);
        $repository->method('save')->willReturnCallback(function (PackTemplateInterface $template): PackTemplateInterface {
            $this->log[] = 'save ' . $template->getName();

            return $template;
        });
        $repository->method('delete')->willReturnCallback(function (PackTemplateInterface $template): bool {
            $this->log[] = 'delete ' . $template->getName();

            return true;
        });
        $synchronization = $this->createStub(SynchronizationInterface::class);
        $synchronization->method('push')->willReturnCallback(function (SyncEntityEnum $kind, SyncableInterface $entry): array {
            \assert($entry instanceof PackTemplate);
            $this->log[] = 'push ' . $kind->value . ' ' . $entry->getName();

            return ['Pack templates: 1 sent to Polkurier.'];
        });
        $locks = $this->createStub(LockManagerInterface::class);
        $locks->method('lock')->willReturnCallback(function (string $name): bool {
            if ($this->syncRunning) {
                return false;
            }
            $this->log[] = 'lock ' . $name;

            return true;
        });
        $locks->method('unlock')->willReturnCallback(function (string $name): bool {
            $this->log[] = 'unlock ' . $name;

            return true;
        });

        return new PackTemplateHandler(
            $repository,
            $this->createStub(PackTemplateFactory::class),
            $synchronization,
            new FixedDateTime((int)\strtotime('2026-09-30 10:00:00 UTC')),
            new SyncLock($locks),
        );
    }

    private function template(): PackTemplate
    {
        /** @var PackTemplate $template */
        $template = (new ObjectManager($this))->getObject(PackTemplate::class);

        return $template->setWebsiteId(1);
    }
}
