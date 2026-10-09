<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversNothing]
class SyncableEntityAclTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string, 1: string}>
     */
    public static function controllers(): array
    {
        $resources = [
            'Address' => 'Polkurier_Shipping::addresses',
            'Bankaccount' => 'Polkurier_Shipping::bank_accounts',
            'Packtemplate' => 'Polkurier_Shipping::pack_templates',
        ];
        $cases = [];
        foreach ($resources as $directory => $resource) {
            foreach (\glob(__DIR__ . '/../../../../Controller/Adminhtml/' . $directory . '/*.php') ?: [] as $file) {
                /** @var class-string $class */
                $class = 'Polkurier\\Shipping\\Controller\\Adminhtml\\' . $directory . '\\' . \basename($file, '.php');
                $cases[$directory . '/' . \basename($file, '.php')] = [$class, $resource];
            }
        }

        return $cases;
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('controllers')]
    public function testEveryActionIsGuardedByItsKindsResource(string $class, string $resource): void
    {
        self::assertSame($resource, \constant($class . '::ADMIN_RESOURCE'));
    }
}
