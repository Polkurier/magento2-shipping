<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Config\Comment;

use Magento\Config\Model\Config\CommentInterface;
use Magento\Framework\Escaper;
use Polkurier\Shipping\Model\Sdk\ApplicationId;

/**
 * Says what the orders are identified by, with this installation's id.
 */
readonly class ApplicationIdComment implements CommentInterface
{
    public function __construct(
        private ApplicationId $applicationId,
        private Escaper $escaper,
    ) {}

    /**
     * @param mixed $elementValue
     */
    public function getCommentText($elementValue): string
    {
        return $this->escaper->escapeHtml((string)\__(
            'Sent with each Polkurier order, with this installation\'s id, %1, so that an order whose answer was lost is found exactly. A new shipment\'s number is taken before the order is placed: a refused order leaves a gap in shipment numbers.',
            $this->applicationId->get(),
        ));
    }
}
