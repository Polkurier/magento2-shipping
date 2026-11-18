<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Magento\Framework\ObjectManagerInterface;

/**
 * An object manager that creates what it's asked for with "new" and the arguments given, an
 * interface as the class preferred for it. It lets a test use Magento's real generated factories:
 * `NewingObjectManager::createFactory(\Polkurier\Webservice\Builder\Entity\Order\PackItemBuilderFactory::class)`.
 */
readonly class NewingObjectManager implements ObjectManagerInterface
{
    /**
     * @param array<class-string, class-string> $preferences the class to create for an interface
     */
    public function __construct(
        private array $preferences = [],
    ) {}

    /**
     * A generated factory creating through this object manager.
     *
     * @template T of object
     *
     * @param class-string<T> $factory
     * @param array<class-string, class-string> $preferences
     *
     * @return T
     */
    public static function createFactory(string $factory, array $preferences = []): object
    {
        return new $factory(new self($preferences));
    }

    /**
     * @param string $type
     * @param array<string, mixed> $arguments
     */
    public function create($type, array $arguments = []): object
    {
        $class = $this->preferences[\ltrim($type, '\\')] ?? \ltrim($type, '\\');

        return new $class(...$arguments);
    }

    /**
     * @param string $type
     */
    public function get($type): object
    {
        return $this->create($type);
    }

    /**
     * @param array<string, mixed> $configuration
     */
    public function configure(array $configuration): void
    {
    }
}
