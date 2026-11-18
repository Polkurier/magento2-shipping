<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Ui\Component;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Ui\Component\MassAction\AllowedAction;

/**
 * A listing's mass action shows only to admins allowed to run the action it posts to: the
 * listing's own permission is the action's, or the mass action is an AllowedAction whose
 * "aclResource" argument is. An action without a URL of its own, whose choices come from a
 * provider class, is left to that class.
 */
#[CoversNothing]
class ListingMassActionsAclTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string|null, string|null}> the listing's
     *     permission, the action's URL path, and its class and "aclResource" argument, if any
     */
    public static function provideMassActions(): array
    {
        $cases = [];
        foreach (\glob(__DIR__ . '/../../../../view/adminhtml/ui_component/*_listing.xml') ?: [] as $file) {
            $xml = \simplexml_load_file($file);
            self::assertNotFalse($xml);
            $listingAcl = (string)($xml->xpath('//dataSource//aclResource')[0] ?? '');
            foreach ($xml->xpath('//massaction/action[settings/url]') ?: [] as $action) {
                $class = (string)$action['class'];
                $argument = $action->xpath('argument[@name="aclResource"]')[0] ?? null;
                $name = \basename($file, '.xml') . ' ' . $action['name'];
                $cases[$name] = [
                    $listingAcl,
                    (string)($action->xpath('settings/url/@path')[0] ?? ''),
                    $class !== '' ? $class : null,
                    $argument !== null ? (string)$argument : null,
                ];
            }
        }

        return $cases;
    }

    #[DataProvider('provideMassActions')]
    public function testOnlyThoseAllowedToRunTheActionSeeIt(
        string $listingAcl,
        string $path,
        ?string $class,
        ?string $aclArgument,
    ): void {
        $required = self::findAdminResource($path);
        $byClass = $class !== null && \is_a($class, AllowedAction::class, true) ? $aclArgument : null;

        self::assertContains($required, [$listingAcl, $byClass]);
    }

    public function testMassActionsAreFound(): void
    {
        self::assertGreaterThan(5, \count(self::provideMassActions()));
    }

    /**
     * The ADMIN_RESOURCE of the controller whose ROUTE is the path.
     */
    private static function findAdminResource(string $path): string
    {
        $root = \realpath(__DIR__ . '/../../../../Controller/Adminhtml');
        self::assertNotFalse($root);
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            $relative = \substr((string)$file->getPathname(), \strlen($root) + 1, -4);
            $class = 'Polkurier\\Shipping\\Controller\\Adminhtml\\' . \str_replace('/', '\\', $relative);
            if (\class_exists($class) && \defined($class . '::ROUTE')
                && \strcasecmp((string)\constant($class . '::ROUTE'), $path) === 0
            ) {
                return (string)\constant($class . '::ADMIN_RESOURCE');
            }
        }
        self::fail('No controller has the route ' . $path . '.');
    }
}
