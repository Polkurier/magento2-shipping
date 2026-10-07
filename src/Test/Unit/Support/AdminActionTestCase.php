<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Support;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\Admin\FlashMessageCollectorFactory;

/**
 * Runs admin actions without Magento: the request answers from $params and $post, the messages
 * the action adds are kept by type in $messages, and the redirect and JSON it answers with are
 * kept in $redirect and $json. The admin may do anything but the ACL resources in $denied.
 */
abstract class AdminActionTestCase extends TestCase
{
    /**
     * @var array<string, mixed>
     */
    protected array $params = [];

    /**
     * @var array<string, mixed>
     */
    protected array $post = [];

    /**
     * @var array<string, string[]> by type: error, warning, notice, success
     */
    protected array $messages = [];

    /**
     * @var array{0: string, 1: array<string, mixed>}|null the path and parameters redirected to
     */
    protected ?array $redirect = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $json = null;

    /**
     * @var string[] the ACL resources the admin hasn't
     */
    protected array $denied = [];

    protected function context(): Context
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')
            ->willReturnCallback(fn(string $name, mixed $default = null): mixed => $this->params[$name] ?? $default);
        $request->method('getParams')->willReturnCallback(fn(): array => $this->params);
        $request->method('getPostValue')
            ->willReturnCallback(
                fn(?string $key = null): mixed => $key === null ? $this->post : ($this->post[$key] ?? null),
            );

        $messages = $this->createStub(ManagerInterface::class);
        foreach (['error', 'warning', 'notice', 'success'] as $type) {
            $messages->method('add' . \ucfirst($type) . 'Message')
                ->willReturnCallback(function ($message) use ($type, $messages): ManagerInterface {
                    $this->messages[$type][] = (string)$message;

                    return $messages;
                });
        }

        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')
            ->willReturnCallback(function (string $path, array $params = []) use ($redirect): Redirect {
                $this->redirect = [$path, $params];

                return $redirect;
            });
        $redirects = $this->createStub(RedirectFactory::class);
        $redirects->method('create')->willReturn($redirect);

        $authorization = $this->createStub(AuthorizationInterface::class);
        $authorization->method('isAllowed')
            ->willReturnCallback(fn(string $resource): bool => !\in_array($resource, $this->denied, true));

        return (new ObjectManager($this))->getObject(Context::class, [
            'request' => $request,
            'authorization' => $authorization,
            'messageManager' => $messages,
            'resultRedirectFactory' => $redirects,
        ]);
    }

    protected function flashMessageCollectorFactory(): FlashMessageCollectorFactory
    {
        return NewingObjectManager::factory(FlashMessageCollectorFactory::class);
    }

    protected function jsonFactory(): JsonFactory
    {
        $json = $this->createStub(Json::class);
        $json->method('setData')->willReturnCallback(function (array $data) use ($json): Json {
            $this->json = $data;

            return $json;
        });
        $factory = $this->createStub(JsonFactory::class);
        $factory->method('create')->willReturn($json);

        return $factory;
    }
}
