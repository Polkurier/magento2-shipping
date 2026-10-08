<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Admin;

/**
 * Parameters the admin forms' buttons post along with the form, which the save actions read.
 */
final class FormActions
{
    /**
     * The parameter that says where to go after saving.
     */
    public const string BACK_PARAM = 'back';

    /**
     * Its value for "Save and Continue Edit": back to the form.
     */
    public const string SAVE_AND_CONTINUE = 'continue';
}
