<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Plugin\Adminhtml;

use Magento\Backend\App\Action;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Api\ShipmentRepositoryInterface;
use Magento\Sales\Model\Order\Shipment;
use Magento\Shipping\Controller\Adminhtml\Order\Shipment\CreateLabel;
use Magento\Shipping\Model\Shipping\LabelGenerator;
use Polkurier\Shipping\Controller\Adminhtml\Packtemplate\Save as PackTemplateSave;
use Polkurier\Shipping\Controller\Adminhtml\Shipment\Save as ShipmentSave;
use Polkurier\Shipping\Model\Carrier\Polkurier;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelPackageMapper;
use Polkurier\Shipping\Model\Shipment\Native\NativeLabelService;
use Polkurier\Shipping\Model\Shipment\OrderLoader;
use Polkurier\Shipping\Model\Shipment\Placement\ManualEntrySaver;

/**
 * What Magento's shipment pages know and the label flow needs, for orders placed with a Polkurier
 * method ({@see NativeLabelService}):
 *
 * - the options posted with the label request, put on the Magento shipment for the carrier
 *   ({@see LabelGenerator::create()}, which has both);
 * - the permissions behind the label, on Magento's {@see \Magento\Shipping\Controller\Adminhtml\Order\Shipment\Save}
 *   (with a label) and {@see CreateLabel} (a label for a shipment made without one, given by its
 *   id). Both answer the packaging window in JSON and check only Magento's own permissions. Asking
 *   for the label places a Polkurier order, so it needs the permission to place them. Without the
 *   permission to change the pack templates, the name to keep a package under is dropped, with a
 *   warning;
 * - after either, what became of the entries typed in to be kept ({@see ManualEntrySaver}).
 */
readonly class NativeLabelPagePlugin
{
    public function __construct(
        private OrderLoader $orderLoader,
        private ManualEntrySaver $manualEntrySaver,
        private ShipmentRepositoryInterface $shipmentRepository,
        private AuthorizationInterface $authorization,
        private ManagerInterface $messageManager,
        private JsonFactory $jsonFactory,
    ) {}

    public function aroundExecute(Action $subject, callable $proceed): mixed
    {
        $request = $subject->getRequest();
        $data = (array)$request->getParam('shipment', []);
        $labelAsked = $subject instanceof CreateLabel || !empty($data['create_shipping_label']);
        if (!$labelAsked || $this->orderLoader->findForPolkurier($this->orderId($request)) === null) {
            return $proceed();
        }
        if (!$this->authorization->isAllowed(ShipmentSave::ADMIN_RESOURCE)) {
            return $this->jsonFactory->create()->setData([
                'error' => true,
                'message' => (string)__('You are not allowed to place Polkurier orders.'),
            ]);
        }
        if (!$this->authorization->isAllowed(PackTemplateSave::ADMIN_RESOURCE)) {
            $request->setParams(['packages' => $this->withoutKeepAs((array)$request->getParam('packages', []))]);
        }
        $result = $proceed();
        $this->manualEntrySaver->takeOutcome()->send($this->messageManager);

        return $result;
    }

    public function beforeCreate(LabelGenerator $subject, Shipment $shipment, RequestInterface $request): null
    {
        $options = $request->getParam(NativeLabelService::OPTIONS_PARAM);
        if (\is_array($options) && Polkurier::isOrderOf($shipment->getOrder())) {
            $shipment->setData(NativeLabelService::FIELD_OPTIONS, $options);
        }

        return null;
    }

    private function orderId(RequestInterface $request): int
    {
        $orderId = (int)$request->getParam('order_id');
        $shipmentId = (int)$request->getParam('shipment_id');
        if ($orderId > 0 || $shipmentId <= 0) {
            return $orderId;
        }
        try {
            return (int)$this->shipmentRepository->get($shipmentId)->getOrderId();
        } catch (NoSuchEntityException) {
            // Magento's action reports the missing shipment.
            return 0;
        }
    }

    /**
     * @param array<int|string, mixed> $packages as Magento posts them
     *
     * @return array<int|string, mixed>
     */
    private function withoutKeepAs(array $packages): array
    {
        foreach ($packages as $key => $package) {
            $name = \is_array($package)
                ? \trim((string)($package['params'][NativeLabelPackageMapper::KEEP_AS_PARAM] ?? ''))
                : '';
            if ($name === '') {
                continue;
            }
            unset($packages[$key]['params'][NativeLabelPackageMapper::KEEP_AS_PARAM]);
            $this->messageManager->addWarningMessage(__(
                '"%1" wasn\'t saved to %2: you aren\'t allowed to change them.',
                $name,
                \mb_strtolower((string)__('Pack templates')),
            ));
        }

        return $packages;
    }
}
