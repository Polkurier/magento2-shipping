<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Backend\App\AbstractAction;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every admin action says itself which ACL resource guards it: one inherited from a base class
 * would let a new action slip under the wrong one, or Magento_Backend::admin.
 */
#[CoversNothing]
class AdminActionsAclTest extends TestCase
{
    /**
     * Magento's own resources the module's actions may use.
     */
    private const array MAGENTO_RESOURCES = ['Magento_Shipping::carriers'];

    /**
     * @return array<string, array{class-string<AbstractAction>}>
     */
    public static function actions(): array
    {
        $root = \realpath(__DIR__ . '/../../../../Controller/Adminhtml');
        self::assertNotFalse($root);
        $cases = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = \substr((string)$file->getPathname(), \strlen($root) + 1, -4);
            $class = 'Polkurier\\Shipping\\Controller\\Adminhtml\\' . \str_replace('/', '\\', $relative);
            if (!\class_exists($class) || !\is_subclass_of($class, AbstractAction::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }
            $cases[$relative] = [$class];
        }
        \ksort($cases);

        return $cases;
    }

    /**
     * @param class-string<AbstractAction> $class
     */
    #[DataProvider('actions')]
    public function testEveryActionDeclaresAResourceThatExists(string $class): void
    {
        $constant = new \ReflectionClassConstant($class, 'ADMIN_RESOURCE');

        self::assertSame($class, $constant->getDeclaringClass()->getName(), 'Declared on the action itself');
        self::assertContains($constant->getValue(), \array_merge(self::resources(), self::MAGENTO_RESOURCES));
    }

    public function testEveryActionIsFound(): void
    {
        self::assertGreaterThan(50, \count(self::actions()));
    }

    /**
     * @return string[] the resources etc/acl.xml declares
     */
    private static function resources(): array
    {
        $xml = \simplexml_load_file(__DIR__ . '/../../../../etc/acl.xml');
        self::assertNotFalse($xml);

        return \array_map('strval', $xml->xpath('//resource/@id') ?: []);
    }
}
