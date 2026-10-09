<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Config\ConfigProvider;

/**
 * Each setting is read at the scope it can be set at: the Polkurier account's for the whole
 * installation, the rest per store view, as the configuration page offers them.
 */
#[CoversClass(ConfigProvider::class)]
#[AllowMockObjectsWithoutExpectations]
class ConfigProviderTest extends TestCase
{
    private const int STORE_ID = 3;

    /**
     * @var array<int, array{string, string, int|string|null}> path, scope and scope id of each read
     */
    private array $reads = [];

    public function testAccountSettingsAreReadForTheWholeInstallationAndTheRestPerStoreView(): void
    {
        $config = $this->config();
        foreach ((new \ReflectionClass(ConfigProvider::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isStatic()) {
                continue;
            }
            $perStore = $method->getNumberOfParameters() > 0;
            $reads = $this->readsOf($config, $method);

            self::assertNotSame([], $reads, $method->getName() . ' reads a setting');
            foreach ($reads as [$path, $scope, $scopeId]) {
                if ($perStore) {
                    self::assertSame(
                        [ScopeInterface::SCOPE_STORE, self::STORE_ID],
                        [$scope, $scopeId],
                        $method->getName() . ' reads ' . $path . ' for the store view it is given',
                    );
                } else {
                    self::assertSame(
                        ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                        $scope,
                        $method->getName() . ' reads ' . $path . ' for the whole installation',
                    );
                }
            }
        }
    }

    public function testTheConfigurationPageOffersEachSettingAtTheScopeItIsReadAt(): void
    {
        $fields = $this->fields();
        $config = $this->config();
        $checked = 0;
        foreach ((new \ReflectionClass(ConfigProvider::class))->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->isConstructor() || $method->isStatic()) {
                continue;
            }
            $perStore = $method->getNumberOfParameters() > 0;
            foreach ($this->readsOf($config, $method) as [$path]) {
                if (!isset($fields[$path])) {
                    // Not on the page, such as the API and map hosts.
                    continue;
                }
                self::assertSame($perStore, $fields[$path], $path . ($perStore
                    ? ' is read per store view, so the page offers it below the default scope'
                    : ' is read for the whole installation, so the page offers it at the default scope only'));
                $checked++;
            }
        }
        self::assertGreaterThan(30, $checked);
    }

    /**
     * @return array<int, array{string, string, int|string|null}> what the getter read: path, scope and scope id
     */
    private function readsOf(ConfigProvider $config, \ReflectionMethod $getter): array
    {
        $this->reads = [];
        $getter->invoke($config, ...($getter->getNumberOfParameters() > 0 ? [self::STORE_ID] : []));

        return $this->reads;
    }

    /**
     * @return array<string, bool> whether each field is offered below the default scope, by path
     */
    private function fields(): array
    {
        $xml = \simplexml_load_file(__DIR__ . '/../../../../etc/adminhtml/system.xml');
        self::assertNotFalse($xml);
        $fields = [];
        foreach ($xml->xpath('//section[@id="carriers"]/group[@id="polkurier"]/group') ?: [] as $group) {
            foreach ($group->field as $field) {
                $path = isset($field->config_path)
                    ? (string)$field->config_path
                    : 'carriers/polkurier/' . $group['id'] . '/' . $field['id'];
                $fields[$path] = (string)$field['showInWebsite'] === '1' || (string)$field['showInStore'] === '1';
            }
        }

        return $fields;
    }

    /**
     * Reads answer with the module's defaults (etc/config.xml), so every getter has a real value.
     */
    private function config(): ConfigProvider
    {
        $xml = \simplexml_load_file(__DIR__ . '/../../../../etc/config.xml');
        self::assertNotFalse($xml);
        $defaults = [];
        $walk = static function (\SimpleXMLElement $node, string $path) use (&$walk, &$defaults): void {
            if ($node->count() === 0) {
                $defaults[$path] = (string)$node;

                return;
            }
            foreach ($node->children() as $child) {
                $walk($child, $path . '/' . $child->getName());
            }
        };
        $walk($xml->default->carriers->polkurier, 'carriers/polkurier');
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(function (
            string $path,
            string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            int|string|null $scopeId = null,
        ) use ($defaults): mixed {
            $this->reads[] = [$path, $scope, $scopeId];

            return $defaults[$path] ?? null;
        });
        $scopeConfig->method('isSetFlag')->willReturnCallback(function (
            string $path,
            string $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
            int|string|null $scopeId = null,
        ) use ($defaults): bool {
            $this->reads[] = [$path, $scope, $scopeId];

            return (bool)($defaults[$path] ?? false);
        });
        $encryptor = $this->createStub(EncryptorInterface::class);
        $encryptor->method('decrypt')->willReturnArgument(0);

        return new ConfigProvider($scopeConfig, $encryptor);
    }
}
