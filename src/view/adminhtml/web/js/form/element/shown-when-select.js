/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
/**
 * A select shown only for some choices of the form (./shown-when).
 */
define(['Magento_Ui/js/form/element/select', './shown-when'], function (Select, shownWhen) {
    'use strict';

    return Select.extend(shownWhen);
});
