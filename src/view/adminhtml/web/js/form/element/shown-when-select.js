/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * A select shown only for some choices of the form (./shown-when).
 */
define(['Magento_Ui/js/form/element/select', './shown-when'], function (Select, shownWhen) {
    'use strict';

    return Select.extend(shownWhen);
});
