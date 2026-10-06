/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * Magento's orders list opened with /carrier/<code>/ in its address, as the Polkurier menu opens
 * it, starts filtered to that carrier's orders: the filter shows, and is cleared, as any other.
 * Magento's own URL filter applier, which waits for the list's saved state, given the filter by
 * the address's path rather than its query, which a menu item can't carry.
 */
define(['Magento_Ui/js/grid/url-filter-applier'], function (UrlFilterApplier) {
    'use strict';

    const OPENED = /\/sales\/order\/index\/(?:.+\/)?carrier\/([A-Za-z0-9_]+)(?:\/|$)/;

    return UrlFilterApplier.extend({
        defaults: {
            listingNamespace: 'sales_order_grid'
        },

        /**
         * @returns {Object}
         */
        getFilterParam: function () {
            const opened = OPENED.exec(window.location.pathname);

            return opened ? {shipping_carrier: opened[1]} : {};
        }
    });
});
