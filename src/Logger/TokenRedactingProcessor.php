<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Logger;

use Monolog\LogRecord;
use Polkurier\Webservice\Request\Account\ChangePasswordMessage;
use Polkurier\Webservice\Request\Account\PinMessage;
use Polkurier\Webservice\Request\BankAccount\DeleteBankAccountMessage;
use Polkurier\Webservice\Request\Common\TokenMessage;
use Polkurier\Webservice\Request\MoneyBox\CreateMoneyBoxPayoutMessage;
use Polkurier\Webservice\Request\TwoFactorToken\VerifyMessage;
use Polkurier\Webservice\Response\Account\AccountVerificationMessage;
use Psr\Http\Message\MessageInterface;

/**
 * Keeps the API token and every other secret out of the log.
 *
 * Every authenticated request body carries the token, and the SDK hands its logger the body
 * verbatim — both inside the message and as the PSR-7 request in the context, and its answer as the
 * PSR-7 response. Confirmations add PINs, codes and two-factor tokens; answers add access tokens
 * and phone PINs. This replaces their values in the message and anywhere in the context, and swaps
 * request and response objects for a redacted copy of their body.
 *
 * A plain callable rather than Monolog's {@see \Monolog\Processor\ProcessorInterface}, whose
 * signature differs between Monolog 2 (array records, Magento 2.4.7) and Monolog 3
 * ({@see LogRecord}, Magento 2.4.8 and later): Monolog takes any callable as a processor, and this
 * one handles both kinds of record.
 */
readonly class TokenRedactingProcessor
{
    public const string MASK = '***';

    /**
     * The phone PIN in Polkurier's account info answer, which the SDK doesn't read.
     */
    private const string FIELD_PHONE_PIN = 'phone_pin';

    /**
     * Wire names whose values are secrets, wherever they appear in a JSON body.
     */
    private const array SECRET_FIELDS = [
        TokenMessage::FIELD_TOKEN,
        AccountVerificationMessage::FIELD_ACCESS_TOKEN,
        CreateMoneyBoxPayoutMessage::FIELD_TWO_FACTOR_TOKEN,
        CreateMoneyBoxPayoutMessage::FIELD_TWO_FACTOR_CODE,
        VerifyMessage::FIELD_CODE,
        DeleteBankAccountMessage::FIELD_ACCESS_PIN,
        CreateMoneyBoxPayoutMessage::FIELD_SECURITY_PIN,
        self::FIELD_PHONE_PIN,
        PinMessage::FIELD_PIN,
        ChangePasswordMessage::FIELD_PASSWORD,
        ChangePasswordMessage::FIELD_PREVIOUS_PASSWORD,
    ];

    /**
     * @param LogRecord|array<string, mixed> $record
     *
     * @return LogRecord|array<string, mixed> the same kind of record
     */
    public function __invoke(LogRecord|array $record): LogRecord|array
    {
        if (\is_array($record)) {
            $record['message'] = self::redact((string)($record['message'] ?? ''));
            $record['context'] = self::redactArray((array)($record['context'] ?? []));

            return $record;
        }

        return $record->with(message: self::redact($record->message), context: self::redactArray($record->context));
    }

    public static function redact(string $text): string
    {
        // Built once: it's applied to every logged request and answer.
        static $pattern = null;
        $pattern ??= '/("(?:'
            . \implode('|', \array_map(
                static fn(string $field): string => \preg_quote($field, '/'),
                self::SECRET_FIELDS,
            ))
            . ')"\s*:\s*)("(?:[^"\\\\]|\\\\.)*"|\d+)/i';

        return (string)\preg_replace($pattern, '$1"' . self::MASK . '"', $text);
    }

    /**
     * @param array<mixed> $values
     *
     * @return array<mixed>
     */
    private static function redactArray(array $values): array
    {
        foreach ($values as $key => $value) {
            $values[$key] = \is_string($key)
                && \in_array(\strtolower($key), self::SECRET_FIELDS, true)
                && \is_scalar($value)
                ? self::MASK
                : self::redactValue($value);
        }

        return $values;
    }

    private static function redactValue(mixed $value): mixed
    {
        return match (true) {
            $value instanceof MessageInterface => self::redact(self::body($value)),
            \is_string($value) => self::redact($value),
            \is_array($value) => self::redactArray($value),
            default => $value,
        };
    }

    /**
     * The whole body, with the stream rewound after: whoever holds the message may read it again.
     */
    private static function body(MessageInterface $message): string
    {
        $stream = $message->getBody();
        $body = (string)$stream;
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        return $body;
    }
}
