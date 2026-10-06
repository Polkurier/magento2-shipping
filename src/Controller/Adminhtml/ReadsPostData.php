<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Controller\Adminhtml;

use Magento\Framework\App\Request\Http;
use Polkurier\Shipping\Model\Admin\FormActions;

/**
 * For the admin actions that save a form: the posted fields, and nothing from the query string.
 *
 * For controllers extending Magento\Backend\App\Action.
 */
trait ReadsPostData
{
    /**
     * @return array<string, mixed>
     */
    protected function getPostData(): array
    {
        $request = $this->getRequest();

        return $request instanceof Http ? (array)$request->getPostValue() : [];
    }

    /**
     * Whether the form was saved with "Save and Continue Edit", to come back to it.
     */
    protected function isSaveAndContinue(): bool
    {
        return $this->getRequest()->getParam(FormActions::BACK_PARAM) === FormActions::SAVE_AND_CONTINUE;
    }
}
