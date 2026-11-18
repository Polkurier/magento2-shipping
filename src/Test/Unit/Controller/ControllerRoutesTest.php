<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A controller's ROUTE, which the classes linking to it build its URL from, leads to that controller:
 * a route id from routes.xml, the controller's directory and its class, as Magento's router reads them.
 */
#[CoversNothing]
class ControllerRoutesTest extends TestCase
{
    /**
     * @return array<string, array{class-string, string}> each controller declaring a ROUTE, with its path
     *     below Controller/
     */
    public static function provideRoutedControllers(): array
    {
        $root = \realpath(__DIR__ . '/../../../Controller');
        self::assertNotFalse($root);
        $cases = [];
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $relative = \substr((string)$file->getPathname(), \strlen($root) + 1, -4);
            $class = 'Polkurier\\Shipping\\Controller\\' . \str_replace('/', '\\', $relative);
            if (\class_exists($class) && \defined($class . '::ROUTE')) {
                $cases[$relative] = [$class, $relative];
            }
        }
        \ksort($cases);

        return $cases;
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideRoutedControllers')]
    public function testTheRouteLeadsToItsController(string $class, string $relative): void
    {
        $route = (string)\constant($class . '::ROUTE');
        $parts = \explode('/', $relative);
        $area = $parts[0] === 'Adminhtml' ? 'adminhtml' : 'frontend';
        if ($area === 'adminhtml') {
            \array_shift($parts);
        }
        [$routeId, $controller, $action] = \explode('/', $route);

        self::assertContains($routeId, self::listRouteIds($area));
        // The router reads an action's underscores as directories.
        self::assertSame($parts, [\ucfirst($controller), ...\array_map('ucfirst', \explode('_', $action))]);
    }

    public function testRoutedControllersAreFound(): void
    {
        self::assertGreaterThan(20, \count(self::provideRoutedControllers()));
    }

    /**
     * @return string[] the route ids the area's routes.xml declares
     */
    private static function listRouteIds(string $area): array
    {
        $xml = \simplexml_load_file(__DIR__ . '/../../../etc/' . $area . '/routes.xml');
        self::assertNotFalse($xml);

        return \array_map('strval', $xml->xpath('//route/@id') ?: []);
    }
}
