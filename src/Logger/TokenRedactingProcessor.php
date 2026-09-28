<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Logger;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Psr\Http\Message\RequestInterface;

/**
 * Keeps the API token out of the log.
 *
 * Every authenticated request body carries the token, and the SDK hands its logger the body
 * verbatim — both inside the message and as the PSR-7 request in the context. This replaces the
 * token's value in the message and swaps the request object for a redacted copy of its body.
 */
class TokenRedactingProcessor implements ProcessorInterface
{
    public const string MASK = '***';

    /**
     * Wire names whose values are secrets, wherever they appear in a JSON body.
     */
    private const array SECRET_FIELDS = ['token', 'access_pin', 'security_pin', 'two_factor_code', 'pin'];

    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        foreach ($context as $key => $value) {
            if ($value instanceof RequestInterface) {
                $context[$key] = self::redact((string)$value->getBody());
            } elseif (\is_string($value)) {
                $context[$key] = self::redact($value);
            }
        }

        return $record->with(message: self::redact($record->message), context: $context);
    }

    public static function redact(string $text): string
    {
        $fields = \implode('|', \array_map(static fn(string $field): string => \preg_quote($field, '/'), self::SECRET_FIELDS));

        return (string)\preg_replace(
            '/("(?:' . $fields . ')"\s*:\s*)("(?:[^"\\\\]|\\\\.)*"|\d+)/i',
            '$1"' . self::MASK . '"',
            $text,
        );
    }
}
