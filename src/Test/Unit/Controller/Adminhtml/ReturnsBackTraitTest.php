<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Controller\Adminhtml;

use Magento\Backend\Model\UrlInterface;
use Magento\Framework\App\Response\RedirectInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Controller\Adminhtml\ReturnsBackTrait;

#[CoversTrait(ReturnsBackTrait::class)]
#[AllowMockObjectsWithoutExpectations]
class ReturnsBackTraitTest extends TestCase
{
    /**
     * @var array{0: string, 1: mixed}|null
     */
    private ?array $target = null;

    public function testBackToWhereTheActionWasStarted(): void
    {
        $this->action('http://shop.test/admin/polkurier/shipment/index/key/abc/')
            ->back('sales/order/view', ['order_id' => 42], []);

        self::assertSame(['url', 'http://shop.test/admin/polkurier/shipment/index/key/abc/'], $this->target);
    }

    public function testWithoutAnyThereTheFallback(): void
    {
        // Magento names the site's base address when there's no referer, or an outside one.
        $this->action('http://shop.test/')->back('sales/order/view', ['order_id' => 42], []);

        self::assertSame(['path', ['sales/order/view', ['order_id' => 42]]], $this->target);
    }

    public function testNotToAStorefrontPage(): void
    {
        // With a custom admin URL, Magento's fallback is the storefront's base address.
        $this->action('https://www.shop.test/')->back('sales/order/view', ['order_id' => 42], []);

        self::assertSame(['path', ['sales/order/view', ['order_id' => 42]]], $this->target);
    }

    public function testNotToAPageTheActionDidAwayWith(): void
    {
        $this->action('http://shop.test/admin/polkurier/shipment/view/id/5/key/abc/')
            ->back('sales/order/view', ['order_id' => 42], ['polkurier/shipment/view/id/5/']);

        self::assertSame(['path', ['sales/order/view', ['order_id' => 42]]], $this->target);
    }

    private function action(string $referer): object
    {
        $result = $this->createStub(Redirect::class);
        $result->method('setUrl')->willReturnCallback(function (string $url) use ($result): Redirect {
            $this->target = ['url', $url];

            return $result;
        });
        $result->method('setPath')
            ->willReturnCallback(function (string $path, array $params = []) use ($result): Redirect {
                $this->target = ['path', [$path, $params]];

                return $result;
            });
        $factory = $this->createStub(RedirectFactory::class);
        $factory->method('create')->willReturn($result);
        $redirect = $this->createStub(RedirectInterface::class);
        $redirect->method('getRefererUrl')->willReturn($referer);
        $url = $this->createStub(UrlInterface::class);
        $url->method('getBaseUrl')->willReturn('http://shop.test/');
        $url->method('getAreaFrontName')->willReturn('admin');

        return new class ($factory, $redirect, $url) {
            use ReturnsBackTrait;

            public function __construct(
                protected RedirectFactory $resultRedirectFactory,
                protected RedirectInterface $_redirect,
                protected UrlInterface $_backendUrl,
            ) {}

            /**
             * @param array<string, mixed> $params
             * @param string[] $gone
             */
            public function back(string $path, array $params, array $gone): Redirect
            {
                return $this->returnBack($path, $params, $gone);
            }
        };
    }
}
