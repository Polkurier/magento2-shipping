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
 * Keeps the API token and every other secret out of the log.
 *
 * Every authenticated request body carries the token, and the SDK hands its logger the body
 * verbatim — both inside the message and as the PSR-7 request in the context. Confirmations add
 * PINs, codes and two-factor tokens. This replaces their values in the message and anywhere in
 * the context, and swaps request objects for a redacted copy of their body.
 */
class TokenRedactingProcessor implements ProcessorInterface
{
    public const string MASK = '***';

    /**
     * Wire names whose values are secrets, wherever they appear in a JSON body.
     */
    private const array SECRET_FIELDS = [
        'token',
        'access_token',
        'two_factor_token',
        'two_factor_code',
        'code',
        'access_pin',
        'security_pin',
        'phone_pin',
        'pin',
        'password',
        'previous_password',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(message: self::redact($record->message), context: self::redactArray($record->context));
    }

    /**
     * @param array<mixed> $values
     * @return array<mixed>
     */
    private static function redactArray(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = \is_string($key) && \in_array(\strtolower($key), self::SECRET_FIELDS, true) && \is_scalar($value)
                ? self::MASK
                : self::redactValue($value);
        }

        return $values;
    }

    private static function redactValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof RequestInterface => self::redact((string)$value->getBody()),
            \is_string($value) => self::redact($value),
            \is_array($value) => self::redactArray($value),
            default => $value,
        };
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
