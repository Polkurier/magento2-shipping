<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Enum\Adminhtml;

/**
 * Where an admin form goes after saving, posted by its buttons in the PARAM parameter and read by
 * the save actions. Without it, the form goes back to its list.
 */
enum AfterSaveEnum: string
{
    /**
     * The parameter that says where to go.
     */
    public const string PARAM = 'back';

    /**
     * "Save and Continue Edit": back to the form.
     */
    case BackToForm = 'continue';
}
