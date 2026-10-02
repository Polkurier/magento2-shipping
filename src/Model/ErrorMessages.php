<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use Polkurier\Shipping\Model\Sdk\RecentFailureException;
use Polkurier\Webservice\Exception\AuthorizationException;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Exception\InvalidArgumentException;
use Polkurier\Webservice\Exception\MalformedResponseException;
use Polkurier\Webservice\Exception\ValidationException;
use Polkurier\Webservice\Enum\Http\ApiErrorCodeEnum;
use Polkurier\Webservice\Exception\WebserviceException;

/**
 * Turns SDK failures into messages a merchant can act on.
 *
 * Never includes the request, which carries the API token.
 */
class ErrorMessages
{
    /**
     * Error codes for a Polkurier that is down or busy, rather than refusing the request itself.
     */
    private const array TRANSIENT_CODES = [
        ApiErrorCodeEnum::UnknownError,
        ApiErrorCodeEnum::MaintenanceMode,
        ApiErrorCodeEnum::TooManyRequests,
        ApiErrorCodeEnum::InternalServerError,
    ];

    /**
     * How Polkurier and the carriers word a passing failure in an uncoded refusal: "try again",
     * "temporarily", "in a moment", "later", "overload". Every other uncoded refusal (a service the
     * carrier doesn't offer, a missing configuration, funds, sizes) stays the same however often asked.
     */
    private const string TRANSIENT_HINT = '/spr[oó]b(uj|ować)\s+ponownie|chwilow|za\s+chwil|później|przeciąż/iu';

    /**
     * The failure as an exception to show the merchant, with the message described. The message
     * is translated already, so it goes into the phrase as it is rather than through a "%1".
     */
    public function exception(\Throwable $e): LocalizedException
    {
        return new LocalizedException(new Phrase($this->describe($e)), $e instanceof \Exception ? $e : null);
    }

    public function describe(\Throwable $e): string
    {
        return match (true) {
            $e instanceof ValidationException => (string)\__(
                'Polkurier would not accept this. Check: %1',
                $this->fields($e->getValidationErrors()),
            ),
            $e instanceof InvalidArgumentException => (string)\__('The request to Polkurier could not be built: %1', $e->getMessage()),
            $e instanceof AuthorizationException => (string)\__(
                // One literal, so that i18n:collect-phrases finds it.
                'Polkurier did not accept the API credentials. Check the customer ID and API token in Stores > Configuration > Sales > Delivery Methods > Polkurier. (%1)',
                $e->getErrorMessage()->getResponse(),
            ),
            $e instanceof WebserviceException => $this->webservice($e),
            $e instanceof ConnectionException => (string)\__(
                'Polkurier could not be reached, or did not answer in time: %1',
                $e->getPrevious()?->getMessage() ?? $e->getMessage(),
            ),
            $e instanceof MalformedResponseException => (string)\__(
                'Polkurier answered with something unexpected (HTTP %1). Try again later; if it persists, contact Polkurier.',
                $e->getResponse()->getStatusCode(),
            ),
            $e instanceof RecentFailureException => $e->getMessage(),
            $e instanceof PolkurierException => (string)\__('Polkurier error: %1', $e->getMessage()),
            default => $e->getMessage(),
        };
    }

    /**
     * Whether asking again, unchanged, may get another answer: Polkurier unreachable, down or busy,
     * or saying so itself. A refusal of the request as it is gets the same answer every time, so
     * there is no point offering to try again; only a change to the request can help.
     */
    public function isTransient(\Throwable $e): bool
    {
        // Through the merchant-facing exceptions to the SDK failure behind them.
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
    public function fieldErrors(\Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => $e->getValidationErrors(),
            $e instanceof WebserviceException => $e->getErrorMessage()->getValidationErrors() ?? [],
            default => [],
        };
    }

    private function webservice(WebserviceException $e): string
    {
        $error = $e->getErrorMessage();
        $message = (string)\__('Polkurier refused: %1', (string)$error->getResponse());
        $fields = $error->getValidationErrors();
        if ($fields) {
            $message .= ' ' . (string)\__('Check: %1', $this->fields($fields));
        }

        return $message;
    }

    /**
     * @param array<string, string[]> $errors
     */
    private function fields(array $errors): string
    {
        $parts = [];
        foreach ($errors as $field => $messages) {
            $parts[] = $field . ' ' . \implode(', ', (array)$messages);
        }

        return \implode('; ', $parts);
    }
}
