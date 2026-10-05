/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
/**
 * A text field shown only for some choices of the form (./shown-when).
 */
define(['Magento_Ui/js/form/element/abstract', './shown-when'], function (Input, shownWhen) {
    'use strict';

    return Input.extend(shownWhen);
});
