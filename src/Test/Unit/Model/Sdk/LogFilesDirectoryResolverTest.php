<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use Magento\Framework\App\Filesystem\DirectoryList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Sdk\LogFilesDirectoryResolver;

#[CoversClass(LogFilesDirectoryResolver::class)]
class LogFilesDirectoryResolverTest extends TestCase
{
    /**
     * @return array<string, array{string, bool}>
     */
    public static function directories(): array
    {
        return [
            'the default' => ['', false],
            'under var' => ['var/polkurier', false],
            'outside Magento' => ['/var/log/polkurier', false],
            'pub itself' => ['pub', true],
            'under pub' => ['pub/media/polkurier', true],
            'under pub, by an absolute path' => ['/srv/magento/pub/static/x', true],
            'under pub, the long way round' => ['var/../pub/x', true],
            'beside pub, named like it' => ['pub-files', false],
        ];
    }

    #[DataProvider('directories')]
    public function testTheWebServersDirectoryIsPublic(string $entered, bool $public): void
    {
        $directory = new LogFilesDirectoryResolver(new DirectoryList('/srv/magento'));

        self::assertSame($public, $directory->isPublic($directory->resolve($entered)));
    }
}
