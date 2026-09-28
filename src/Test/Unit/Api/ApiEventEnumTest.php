<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Api;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Api\ApiEventEnum;

/**
 * Other modules observe these names in their events.xml, so changing one breaks them silently.
 */
#[CoversNothing]
class ApiEventEnumTest extends TestCase
{
    public function testTheEventNamesStayAsPublished(): void
    {
        self::assertSame([
            'polkurier_api_order_create_after',
            'polkurier_api_order_cancel_after',
            'polkurier_api_pickup_create_after',
            'polkurier_api_address_save_after',
            'polkurier_api_address_delete_after',
            'polkurier_api_pack_template_save_after',
            'polkurier_api_pack_template_delete_after',
            'polkurier_api_bank_account_save_after',
            'polkurier_api_bank_account_delete_after',
            'polkurier_api_two_factor_code_request_after',
            'polkurier_api_payout_create_after',
            'polkurier_api_payout_cancel_after',
        ], \array_map(static fn(ApiEventEnum $event): string => $event->value, ApiEventEnum::cases()));
    }
}
