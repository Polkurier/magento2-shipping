<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model\Sdk;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Polkurier\Shipping\Exception\RecentFailureException;
use Polkurier\Webservice\Enum\Http\ApiErrorCodeEnum;
use Polkurier\Webservice\Exception\AuthorizationException;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Exception\InvalidArgumentException;
use Polkurier\Webservice\Exception\MalformedResponseException;
use Polkurier\Webservice\Exception\ValidationException;
use Polkurier\Webservice\Exception\WebserviceException;
use Polkurier\Webservice\Request\Order\CodMessage;
use Polkurier\Webservice\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Request\Order\OrderAddressMessage;
use Polkurier\Webservice\Request\Order\PackMessage;
use Polkurier\Webservice\Request\Order\PickupMessage;

/**
 * Turns SDK failures into messages a merchant can act on.
 *
 * Never includes the request, which carries the API token.
 */
readonly class ErrorMessageResolver
{
    /**
     * Error codes meaning Polkurier is down or busy, rather than refusing the request itself.
     */
    private const array TRANSIENT_CODES = [
        ApiErrorCodeEnum::UnknownError,
        ApiErrorCodeEnum::MaintenanceMode,
        ApiErrorCodeEnum::TooManyRequests,
        ApiErrorCodeEnum::InternalServerError,
    ];

    /**
     * Phrases Polkurier and the carriers use for a passing failure in a refusal without an error
     * code: "try again", "temporarily", "in a moment", "later", "overload". Every other uncoded
     * refusal (a service the carrier doesn't offer, a missing configuration, funds, sizes) gets the
     * same answer however often it's repeated.
     */
    private const string TRANSIENT_HINT = '/spr[oó]b(uj|ować)\s+ponownie|chwilow|za\s+chwil|później|przeciąż/iu';

    /**
     * The failure as an exception to show the merchant, with the message described.
     */
    public function createException(\Exception $e): LocalizedException
    {
        return new LocalizedException($this->createPhrase($e), $e);
    }

    /**
     * The failure described for the merchant, as a phrase to use in an exception or, as it is, in
     * another phrase. An already translated message only fills a placeholder; it isn't translated
     * again.
     */
    public function createPhrase(\Throwable $e): Phrase
    {
        return match (true) {
            $e instanceof ValidationException => __(
                'Polkurier would not accept this. Check: %1',
                $this->formatFields($e->getValidationErrors()),
            ),
            $e instanceof InvalidArgumentException => __(
                'The request to Polkurier could not be built: %1',
                $e->getMessage(),
            ),
            $e instanceof AuthorizationException => __(
                'Polkurier did not accept the API credentials. '
                . 'Check the customer ID and API token in Stores > '
                . 'Configuration > Sales > Delivery Methods > Polkurier. (%1)',
                $e->getErrorMessage()->getResponse(),
            ),
            $e instanceof WebserviceException => $this->describeWebserviceError($e),
            $e instanceof ConnectionException => __(
                'Polkurier could not be reached, or did not answer in time: %1',
                $e->getPrevious()?->getMessage() ?? $e->getMessage(),
            ),
            $e instanceof MalformedResponseException => __(
                'Polkurier answered with something unexpected (HTTP %1). Try again later; '
                . 'if it persists, contact Polkurier.',
                $e->getResponse()->getStatusCode(),
            ),
            // Already described for the merchant when it failed.
            $e instanceof RecentFailureException => __('%1', $e->getMessage()),
            $e instanceof PolkurierException => __('Polkurier error: %1', $e->getMessage()),
            // Translated by whoever threw it, or not ours to translate: used as it is.
            default => __('%1', $e->getMessage()),
        };
    }

    /**
     * Whether asking again, unchanged, may get another answer: Polkurier unreachable, down or busy,
     * or saying so itself. A refusal of the request as it is gets the same answer every time, so
     * there is no point offering to try again; only a change to the request can help.
     */
    public function isTransient(\Throwable $e): bool
    {
        // Unwrap the merchant-facing exceptions to reach the SDK failure behind them.
        while ($e instanceof LocalizedException && $e->getPrevious() !== null) {
            $e = $e->getPrevious();
        }
        if ($e instanceof ConnectionException || $e instanceof MalformedResponseException) {
            return true;
        }
        if (!$e instanceof WebserviceException) {
            return false;
        }
        $error = $e->getErrorMessage();
        $code = ApiErrorCodeEnum::tryFrom((string)$error->getErrorCode())
            ?? ($error->getStatusCode() ? ApiErrorCodeEnum::fromStatusCode($error->getStatusCode()) : null);
        if ($code !== null) {
            return \in_array($code, self::TRANSIENT_CODES, true);
        }
        $reason = \trim(\is_string($error->getResponse()) ? $error->getResponse() : '');

        return $reason === '' || \preg_match(self::TRANSIENT_HINT, $reason) === 1;
    }

    /**
     * Field errors keyed by wire name, as both the SDK and the API report them.
     *
     * @return array<string, string[]>
     */
    public function getFieldErrors(\Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => $e->getValidationErrors(),
            $e instanceof WebserviceException => $e->getErrorMessage()->getValidationErrors() ?? [],
            default => [],
        };
    }

    /**
     * A field as the shipment screen calls it, from its wire name, e.g. "recipient.phone" as
     * "Recipient: Phone" and "packs.0.weight" as "Parcel 1: Weight". A name it doesn't know stays
     * as it is.
     */
    public function getFieldLabel(string $field): string
    {
        $groups = [
            CreateOrderMessage::FIELD_RECIPIENT => __('Recipient'),
            CreateOrderMessage::FIELD_SENDER => __('Sender'),
            CreateOrderMessage::FIELD_COVER_ADDRESS => __('Sender on the label'),
            CreateOrderMessage::FIELD_PACKS => __('Parcel'),
            CreateOrderMessage::FIELD_CASH_ON_DELIVERY => __('Cash on delivery'),
            CreateOrderMessage::FIELD_PICKUP => __('Collection'),
        ];
        $names = [
            OrderAddressMessage::FIELD_PERSON => __('Name'),
            OrderAddressMessage::FIELD_COMPANY => __('Company'),
            OrderAddressMessage::FIELD_STREET => __('Street'),
            OrderAddressMessage::FIELD_HOUSE_NUMBER => __('House number'),
            OrderAddressMessage::FIELD_FLAT_NUMBER => __('Flat number'),
            OrderAddressMessage::FIELD_POSTCODE => __('Postcode'),
            OrderAddressMessage::FIELD_CITY => __('City'),
            OrderAddressMessage::FIELD_PHONE => __('Phone'),
            OrderAddressMessage::FIELD_EMAIL => __('E-mail'),
            OrderAddressMessage::FIELD_POINT_ID => __('Point'),
            OrderAddressMessage::FIELD_COUNTRY => __('Country'),
            PackMessage::FIELD_LENGTH => __('Length'),
            PackMessage::FIELD_WIDTH => __('Width'),
            PackMessage::FIELD_HEIGHT => __('Height'),
            PackMessage::FIELD_WEIGHT => __('Weight'),
            PackMessage::FIELD_TYPE => __('Type'),
            PackMessage::FIELD_AMOUNT => __('Amount'),
            CodMessage::FIELD_AMOUNT => __('Amount'),
            CodMessage::FIELD_BANK_ACCOUNT => __('Bank account'),
            PickupMessage::FIELD_PICKUP_DATE => __('Date'),
            PickupMessage::FIELD_PICKUP_TIME_FROM => __('From'),
            PickupMessage::FIELD_PICKUP_TIME_TO => __('To'),
            CreateOrderMessage::FIELD_DESCRIPTION => __('Shipment description'),
            CreateOrderMessage::FIELD_INSURANCE => __('Insurance'),
            CreateOrderMessage::FIELD_COURIER => __('Carrier'),
        ];
        $parts = \explode('.', $field);
        $group = $groups[$parts[0]] ?? null;
        if ($group === null) {
            return \count($parts) === 1 && isset($names[$field]) ? (string)$names[$field] : $field;
        }
        $rest = \array_slice($parts, 1);
        if ($parts[0] === CreateOrderMessage::FIELD_PACKS && isset($rest[0]) && \ctype_digit($rest[0])) {
            // Counted from 1, as on the screen.
            $group = (string)$group . ' ' . ((int)\array_shift($rest) + 1);
        }
        $words = \array_map(static fn(string $part): string => (string)($names[$part] ?? $part), $rest);

        return $words === [] ? (string)$group : $group . ': ' . \implode(' ', $words);
    }

    private function describeWebserviceError(WebserviceException $e): Phrase
    {
        $error = $e->getErrorMessage();
        $fields = $error->getValidationErrors();

        return $fields
            ? __('Polkurier refused: %1 Check: %2', (string)$error->getResponse(), $this->formatFields($fields))
            : __('Polkurier refused: %1', (string)$error->getResponse());
    }

    /**
     * @param array<string, string[]> $errors
     */
    private function formatFields(array $errors): string
    {
        $parts = [];
        foreach ($errors as $field => $messages) {
            $parts[] = $this->getFieldLabel((string)$field) . ' ' . \implode(', ', (array)$messages);
        }

        return \implode('; ', $parts);
    }
}
