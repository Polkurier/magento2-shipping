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
use Polkurier\Webservice\Entity\Order;
use Polkurier\Webservice\Entity\Order\Address;
use Polkurier\Webservice\Entity\Order\Cod;
use Polkurier\Webservice\Entity\Order\PackItem;
use Polkurier\Webservice\Entity\Order\Pickup;
use Polkurier\Webservice\Enum\Http\ApiErrorCodeEnum;
use Polkurier\Webservice\Enum\Validator\ValidationErrorCodeEnum;
use Polkurier\Webservice\Exception\AuthorizationException;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Exception\InvalidArgumentException;
use Polkurier\Webservice\Exception\MalformedResponseException;
use Polkurier\Webservice\Exception\ValidationException;
use Polkurier\Webservice\Exception\WebserviceException;
use Polkurier\Webservice\Validator\Violation;

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
                $e->getViolations() !== []
                    ? $this->formatViolations($e->getViolations())
                    : $this->formatFields($e->getValidationErrors()),
            ),
            $e instanceof InvalidArgumentException => __(
                'The request to Polkurier could not be built: %1',
                $e->getMessage(),
            ),
            $e instanceof AuthorizationException => __(
                'Polkurier did not accept the API credentials. '
                . 'Check the customer ID and API token in Stores > '
                . 'Configuration > Sales > Delivery Methods > Polkurier. (%1)',
                $e->getErrorMessage()->response,
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
        $code = ApiErrorCodeEnum::tryFrom((string)$error->errorCode)
            ?? ($error->statusCode ? ApiErrorCodeEnum::fromStatusCode($error->statusCode) : null);
        if ($code !== null) {
            return \in_array($code, self::TRANSIENT_CODES, true);
        }
        $reason = \trim($error->response);

        return $reason === '' || \preg_match(self::TRANSIENT_HINT, $reason) === 1;
    }

    /**
     * A field as the shipment screen calls it, from its path in the payload, e.g. "recipient.phone"
     * as "Recipient: Phone", and "packs[1].weight" or "packs.1.weight" as "Parcel 2: Weight". A
     * name it doesn't know stays as it is.
     */
    public function getFieldLabel(string $field): string
    {
        $groups = [
            Order::FIELD_RECIPIENT => __('Recipient'),
            Order::FIELD_SENDER => __('Sender'),
            Order::FIELD_COVER_ADDRESS => __('Sender on the label'),
            Order::FIELD_PACKS => __('Parcel'),
            Order::FIELD_CASH_ON_DELIVERY => __('Cash on delivery'),
            Order::FIELD_PICKUP => __('Collection'),
            Order::FIELD_ADDITIONAL_FIELDS => __('Carrier field'),
        ];
        $names = [
            Address::FIELD_PERSON => __('Name'),
            Address::FIELD_COMPANY => __('Company'),
            Address::FIELD_STREET => __('Street'),
            Address::FIELD_HOUSE_NUMBER => __('House number'),
            Address::FIELD_FLAT_NUMBER => __('Flat number'),
            Address::FIELD_COVER_FLAT_NUMBER => __('Flat number'),
            Address::FIELD_POSTCODE => __('Postcode'),
            Address::FIELD_CITY => __('City'),
            Address::FIELD_PROVINCE => __('Province'),
            Address::FIELD_PHONE => __('Phone'),
            Address::FIELD_EMAIL => __('E-mail'),
            Address::FIELD_POINT_ID => __('Point'),
            Address::FIELD_COUNTRY => __('Country'),
            PackItem::FIELD_LENGTH => __('Length'),
            PackItem::FIELD_WIDTH => __('Width'),
            PackItem::FIELD_HEIGHT => __('Height'),
            PackItem::FIELD_WEIGHT => __('Weight'),
            PackItem::FIELD_TYPE => __('Type'),
            PackItem::FIELD_AMOUNT => __('Amount'),
            Cod::FIELD_AMOUNT => __('Amount'),
            Cod::FIELD_TYPE => __('Type'),
            Cod::FIELD_RETURN_TYPE => __('Paid out to'),
            Cod::FIELD_BANK_ACCOUNT => __('Bank account'),
            Pickup::FIELD_PICKUP_DATE => __('Date'),
            Pickup::FIELD_PICKUP_TIME_FROM => __('From'),
            Pickup::FIELD_PICKUP_TIME_TO => __('To'),
            Order::FIELD_DESCRIPTION => __('Shipment description'),
            Order::FIELD_INSURANCE => __('Insurance'),
            Order::FIELD_COURIER => __('Carrier'),
            Order::FIELD_SHIPMENT_TYPE => __('Shipment type'),
            Order::FIELD_COURIER_SERVICE => __('Additional services'),
        ];
        // A list's entry comes numbered in brackets, packs[1], or as a part of its own, packs.1.
        $parts = \explode('.', (string)\preg_replace('/\[(\d+)]/', '.$1', $field));
        $group = $groups[$parts[0]] ?? null;
        if ($group === null) {
            return \count($parts) === 1 && isset($names[$field]) ? (string)$names[$field] : $field;
        }
        $rest = \array_slice($parts, 1);
        if ($parts[0] === Order::FIELD_PACKS && isset($rest[0]) && \ctype_digit($rest[0])) {
            // Counted from 1, as on the screen.
            $group = (string)$group . ' ' . ((int)\array_shift($rest) + 1);
        }
        $words = \array_map(static fn(string $part): string => (string)($names[$part] ?? $part), $rest);

        return $words === [] ? (string)$group : $group . ': ' . \implode(' ', $words);
    }

    private function describeWebserviceError(WebserviceException $e): Phrase
    {
        $error = $e->getErrorMessage();
        $fields = $error->validationErrors;

        return $fields
            ? __('Polkurier refused: %1 Check: %2', $error->response, $this->formatFields($fields))
            : __('Polkurier refused: %1', $error->response);
    }

    /**
     * The SDK's own refusal, each reason in the admin's language. A reason the module has no words
     * for (one the SDK reports only in English, or one a later SDK adds) stays in English.
     *
     * @param list<Violation> $violations
     */
    private function formatViolations(array $violations): string
    {
        $reasons = [];
        foreach ($violations as $violation) {
            $reasons[$violation->path][] = $this->describeViolation($violation);
        }

        return $this->formatFields($reasons);
    }

    /**
     * Why the field was refused, worded to follow its name.
     */
    private function describeViolation(Violation $violation): string
    {
        $parameters = $violation->parameters;

        return (string)match ($violation->code) {
            ValidationErrorCodeEnum::Blank => $this->describeBlank($violation),
            ValidationErrorCodeEnum::NotAnEmail => __('must be an e-mail address'),
            ValidationErrorCodeEnum::NotAPostcode => __('must be a postcode of %1', $parameters['country'] ?? ''),
            ValidationErrorCodeEnum::NotAnOrderNumber => __('must be a Polkurier order number'),
            ValidationErrorCodeEnum::NotAPhoneNumber => __('must be a phone number'),
            ValidationErrorCodeEnum::PhoneNumberTooShort => __(
                'has too few digits (at least %1)',
                $parameters['minimum'] ?? '',
            ),
            ValidationErrorCodeEnum::NotABankAccount => __('must be a bank account number'),
            ValidationErrorCodeEnum::BankAccountBadCharacters => __('may contain only letters and digits'),
            ValidationErrorCodeEnum::BankAccountWrongLength => __(
                'must have %1 characters for %2',
                $parameters['expected'] ?? '',
                $parameters['country'] ?? '',
            ),
            ValidationErrorCodeEnum::BankAccountWrongCountry => __(
                'must be an account of %1',
                $parameters['country'] ?? '',
            ),
            ValidationErrorCodeEnum::BankAccountBadChecksum => __('has a wrong check digit: check the number'),
            ValidationErrorCodeEnum::NotAWholeNumber => __('must be a whole number'),
            ValidationErrorCodeEnum::NotANumber => __('must be a number'),
            ValidationErrorCodeEnum::OutOfRange => $this->describeRange($parameters) ?? $violation->message,
            ValidationErrorCodeEnum::NotAList => __('must be a list'),
            ValidationErrorCodeEnum::EmptyList => __('must have at least one entry'),
            ValidationErrorCodeEnum::WrongType => __('has a value of the wrong type'),
            ValidationErrorCodeEnum::OutOfOrder => isset($parameters['field'])
                ? __('must not exceed %1', $this->getSiblingLabel($violation, $parameters['field']))
                : $violation->message,
            default => $violation->message,
        };
    }

    /**
     * A required field left empty, and what about another field made it required, if anything.
     */
    private function describeBlank(Violation $violation): Phrase
    {
        $field = $violation->parameters['field'] ?? null;
        if ($field === null) {
            return __('must not be empty');
        }
        $label = $this->getSiblingLabel($violation, $field);

        return match ($violation->parameters['condition'] ?? null) {
            'set' => __('is required when %1 is filled in', $label),
            'not_set' => __('is required when %1 is not set', $label),
            'empty' => __('is required when %1 is empty', $label),
            // The value is a code (e.g. "BA"), which says nothing to the merchant.
            'equals' => __('is required for the chosen %1', $label),
            default => __('is required because of %1', $label),
        };
    }

    /**
     * The range a number must be in, from the bounds it has; null without any.
     *
     * @param array<string, scalar> $parameters
     */
    private function describeRange(array $parameters): ?Phrase
    {
        $minimum = $parameters['minimum'] ?? null;
        $maximum = $parameters['maximum'] ?? null;
        $exclusive = !empty($parameters['exclusiveMinimum']);

        return match (true) {
            $minimum !== null && $maximum !== null => $exclusive
                ? __('must be more than %1 and at most %2', $minimum, $maximum)
                : __('must be from %1 to %2', $minimum, $maximum),
            $minimum !== null => $exclusive
                ? __('must be more than %1', $minimum)
                : __('must be at least %1', $minimum),
            $maximum !== null => __('must be at most %1', $maximum),
            default => null,
        };
    }

    /**
     * The label of the field a violation names in its parameters: a wire name beside the refused
     * field, e.g. "return_cod" for "COD.codbankaccount".
     */
    private function getSiblingLabel(Violation $violation, int|float|string|bool $field): string
    {
        $parent = \strrpos($violation->path, '.');

        return $this->getFieldLabel(
            ($parent === false ? '' : \substr($violation->path, 0, $parent + 1)) . $field,
        );
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
