<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\PickupPoint;

/**
 * The shipping description of an order delivered to a pickup point, with the point: "Paczkomat
 * InPost, pickup point WAW01A (ul. Prosta 1, Warszawa)". It's what Magento prints wherever it
 * names the shipping method, so the customer sees the point in the order's e-mails, their account
 * and printed documents, and the merchant on the order, invoices and packing slips.
 */
readonly class PointDescriptionBuilder
{
    /**
     * The length of the order's shipping_description column.
     */
    private const int MAX_LENGTH = 255;

    public function describe(string $description, string $pointId, ?string $label): string
    {
        $label = \trim((string)$label);
        $text = (string)__(
            '%1, pickup point %2',
            $description,
            $label !== '' ? \sprintf('%s (%s)', $pointId, $label) : $pointId,
        );
        if (\mb_strlen($text) > self::MAX_LENGTH && $label !== '') {
            // The address goes first: the point's code is what the carrier needs.
            $text = (string)__('%1, pickup point %2', $description, $pointId);
        }

        return \mb_substr($text, 0, self::MAX_LENGTH);
    }

    /**
     * Whether the description already names the point, e.g. on an order placed this way.
     */
    public function names(string $description, string $pointId): bool
    {
        return $pointId !== '' && \str_contains($description, $pointId);
    }
}
