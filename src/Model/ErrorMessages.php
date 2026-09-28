<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Model;

use Polkurier\Webservice\Exception\AuthorizationException;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Exception\InvalidArgumentException;
use Polkurier\Webservice\Exception\MalformedResponseException;
use Polkurier\Webservice\Exception\ValidationException;
use Polkurier\Webservice\Exception\WebserviceException;

/**
 * Turns SDK failures into messages a merchant can act on.
 *
 * Never includes the request, which carries the API token.
 */
class ErrorMessages
{
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
                'Polkurier did not accept the API credentials configured for this website. Check the customer ID and API token in Stores > Configuration > Sales > Delivery Methods > Polkurier. (%1)',
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
            $e instanceof PolkurierException => (string)\__('Polkurier error: %1', $e->getMessage()),
            default => $e->getMessage(),
        };
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
