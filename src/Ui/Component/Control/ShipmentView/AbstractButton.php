<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control\ShipmentView;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;

/**
 * A button of the shipment details page. It shows only where the shipments list offers the same
 * action, and runs the list's action for this shipment, coming back to this page.
 */
abstract class AbstractButton implements ButtonProviderInterface
{
    private ?ShipmentInterface $shipment = null;

    public function __construct(
        protected readonly RequestInterface $request,
        protected readonly UrlInterface $url,
        protected readonly AuthorizationInterface $authorization,
        private readonly ShipmentRepositoryInterface $shipmentRepository,
        protected readonly Json $json,
    ) {}

    protected function shipment(): ?ShipmentInterface
    {
        if ($this->shipment === null) {
            try {
                $this->shipment = $this->shipmentRepository->get((int)$this->request->getParam('id'));
            } catch (NoSuchEntityException) {
                return null;
            }
        }

        return $this->shipment;
    }

    protected function isCreated(): bool
    {
        return $this->shipment()?->getState() === ShipmentInterface::STATE_CREATED;
    }

    protected function actionUrl(string $action): string
    {
        return $this->url->getUrl('polkurier/shipment/' . $action, ['id' => (int)$this->shipment()?->getId()]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function link(string $label, string $action, int $sortOrder, string $class = 'secondary'): array
    {
        return [
            'label' => $label,
            'class' => $class,
            'on_click' => \sprintf("setLocation('%s');", $this->actionUrl($action)),
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * A button that posts to the action with the form key, after a confirmation when one is given.
     *
     * @return array<string, mixed>
     */
    protected function post(string $label, string $action, int $sortOrder, ?string $confirmTitle = null, ?string $confirmText = null): array
    {
        $post = $this->json->serialize(['action' => $this->actionUrl($action), 'data' => new \stdClass()]);
        $send = \sprintf('dataPost().postData(%s);', $post);
        $script = $confirmTitle === null
            ? \sprintf("require(['mage/dataPost'], function (dataPost) { %s });", $send)
            : \sprintf(
                "require(['mage/dataPost', 'Magento_Ui/js/modal/confirm'], function (dataPost, confirm) { confirm({title: %s, content: %s, actions: {confirm: function () { %s }}}); });",
                $this->json->serialize($confirmTitle),
                $this->json->serialize((string)$confirmText),
                $send,
            );

        return [
            'label' => $label,
            'class' => 'secondary',
            'on_click' => $script . ' return false;',
            'sort_order' => $sortOrder,
        ];
    }
}
