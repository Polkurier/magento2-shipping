<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config\Backend\ApplicationId;
use Polkurier\Shipping\Model\Config\Backend\LogFilesDirectory;
use Polkurier\Shipping\Model\Sdk\LogFilesDirectoryResolver;

/**
 * The settings checked before they're saved: the installation ID and the directory for logged files.
 */
#[CoversClass(ApplicationId::class)]
#[CoversClass(LogFilesDirectory::class)]
#[AllowMockObjectsWithoutExpectations]
class ValidatedSettingsTest extends TestCase
{
    public function testAnInstallationIdIsSavedTrimmed(): void
    {
        $value = $this->createApplicationId('  shop-1.main_2  ');

        $value->beforeSave();

        self::assertSame('shop-1.main_2', $value->getValue());
    }

    public function testAnEmptyInstallationIdIsSavedForTheComputedOne(): void
    {
        $value = $this->createApplicationId(' ');

        $value->beforeSave();

        self::assertSame('', $value->getValue());
    }

    public function testAnInstallationIdWithOtherCharactersIsRefused(): void
    {
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('up to 50 letters, digits, dots, dashes and underscores');

        $this->createApplicationId('my shop')->beforeSave();
    }

    public function testADirectoryOutsidePubIsSavedTrimmed(): void
    {
        $value = $this->createLogFilesDirectory(' var/polkurier ');

        $value->beforeSave();

        self::assertSame('var/polkurier', $value->getValue());
    }

    public function testADirectoryTheWebServerServesIsRefused(): void
    {
        foreach (['pub/media/polkurier', 'var/../pub', '/shop/pub/static'] as $entered) {
            try {
                $this->createLogFilesDirectory($entered)->beforeSave();
                self::fail($entered . ' is refused.');
            } catch (LocalizedException $e) {
                self::assertStringStartsWith('Logged files can\'t be saved in the pub directory', $e->getMessage());
            }
        }
    }

    private function createApplicationId(string $entered): ApplicationId
    {
        /** @var ApplicationId $value */
        $value = (new ObjectManager($this))->getObject(ApplicationId::class);

        return $value->setValue($entered);
    }

    private function createLogFilesDirectory(string $entered): LogFilesDirectory
    {
        $directoryList = $this->createStub(DirectoryList::class);
        $directoryList->method('getRoot')->willReturn('/shop');
        $directoryList->method('getPath')->willReturnCallback(
            static fn(string $code): string => '/shop/' . $code,
        );
        /** @var LogFilesDirectory $value */
        $value = (new ObjectManager($this))->getObject(LogFilesDirectory::class, [
            'logFilesDirectoryResolver' => new LogFilesDirectoryResolver($directoryList),
        ]);

        return $value->setValue($entered);
    }
}
