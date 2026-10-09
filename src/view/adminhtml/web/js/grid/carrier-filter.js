/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * When Magento's orders list is opened with /carrier/<code>/ in its URL, as the Polkurier menu
 * opens it, it starts filtered to that carrier's orders. The filter shows, and is cleared, like any
 * other.
 *
 * This is Magento's own URL filter applier, which waits for the list's saved state. It only reads
 * the filter from the URL's path rather than its query, since a menu item can't carry a query.
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
