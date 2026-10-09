<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Ui\Component\Control;

use Magento\Framework\Serialize\Serializer\Json;

/**
 * The scripts behind the module's toolbar buttons. Every value they carry, URLs included, is
 * JSON-encoded, so nothing a URL or a text contains can end the script and start another.
 */
readonly class OnClickScriptBuilder
{
    public function __construct(
        private Json $json,
    ) {}

    /**
     * Follows a link.
     */
    public function location(string $url): string
    {
        return \sprintf('setLocation(%s);', $this->json->serialize($url));
    }

    /**
     * Posts to an action with the form key, after a confirmation when a title is given.
     */
    public function post(string $url, ?string $confirmTitle = null, ?string $confirmText = null): string
    {
        // "data" must reach dataPost as an object: it adds the form key to it as a property.
        $send = \sprintf(
            'dataPost().postData(%s);',
            $this->json->serialize(['action' => $url, 'data' => new \stdClass()]),
        );
        $script = $confirmTitle === null
            ? \sprintf("require(['mage/dataPost'], function (dataPost) { %s });", $send)
            : \sprintf(
                "require(['mage/dataPost', 'Magento_Ui/js/modal/confirm'], function (dataPost, confirm) { "
                . 'confirm({title: %s, content: %s, actions: {confirm: function () { %s }}}); });',
                $this->json->serialize($confirmTitle),
                $this->json->serialize((string)$confirmText),
                $send,
            );

        return $script . ' return false;';
    }

    /**
     * Asks for one value, then posts it to an action with the form key, under the given field name.
     */
    public function prompt(string $url, string $title, string $text, string $field): string
    {
        return \sprintf(
            "require(['mage/dataPost', 'Magento_Ui/js/modal/prompt'], function (dataPost, prompt) { "
            . 'prompt({title: %s, content: %s, actions: {confirm: function (value) { '
            . 'if (value && value.trim()) { dataPost().postData({action: %s, data: {%s: value.trim()}}); } '
            . '}}}); }); return false;',
            $this->json->serialize($title),
            $this->json->serialize($text),
            $this->json->serialize($url),
            $this->json->serialize($field),
        );
    }
}
