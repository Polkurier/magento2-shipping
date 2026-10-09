<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Form\ShipmentView;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;
use Polkurier\Shipping\Api\Data\ShipmentInterface;
use Polkurier\Shipping\Api\ShipmentRepositoryInterface;
use Polkurier\Shipping\Ui\Component\Control\OnClickScriptBuilder;

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
        protected readonly OnClickScriptBuilder $onClickScriptBuilder,
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

    /**
     * @param array<string, string> $params besides the shipment's id
     */
    protected function actionUrl(string $action, array $params = []): string
    {
        return $this->url->getUrl(
            'polkurier/shipment/' . $action,
            ['id' => (int)$this->shipment()?->getId()] + $params,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function link(string $label, string $action, int $sortOrder, string $class = 'secondary'): array
    {
        return [
            'label' => $label,
            'class' => $class,
            'on_click' => $this->onClickScriptBuilder->location($this->actionUrl($action)),
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * A button that posts to the action with the form key, after a confirmation when one is given.
     *
     * @return array<string, mixed>
     */
    protected function post(
        string $label,
        string $action,
        int $sortOrder,
        ?string $confirmTitle = null,
        ?string $confirmText = null,
    ): array {
        return [
            'label' => $label,
            'class' => 'secondary',
            'on_click' => $this->onClickScriptBuilder->post($this->actionUrl($action), $confirmTitle, $confirmText),
            'sort_order' => $sortOrder,
        ];
    }
}
