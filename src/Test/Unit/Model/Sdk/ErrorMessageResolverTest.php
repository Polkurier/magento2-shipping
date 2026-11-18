<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * Licensed under the MIT License, see LICENSE.md
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model\Sdk;

use Magento\Framework\Exception\LocalizedException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Exception\RecentFailureException;
use Polkurier\Shipping\Model\Sdk\ErrorMessageResolver;
use Polkurier\Webservice\Dto\Request\Order\CreateOrderMessage;
use Polkurier\Webservice\Dto\Response\ErrorMessage;
use Polkurier\Webservice\Entity\Order;
use Polkurier\Webservice\Entity\Order\Address;
use Polkurier\Webservice\Entity\Order\Cod;
use Polkurier\Webservice\Entity\Order\PackItem;
use Polkurier\Webservice\Entity\Order\Pickup;
use Polkurier\Webservice\Enum\Http\ResponseStatusEnum;
use Polkurier\Webservice\Enum\Order\PackTypeEnum;
use Polkurier\Webservice\Enum\Order\ShipmentTypeEnum;
use Polkurier\Webservice\Enum\Validator\ValidationErrorCodeEnum;
use Polkurier\Webservice\Exception\AuthorizationException;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Exception\Exception as PolkurierException;
use Polkurier\Webservice\Exception\InvalidArgumentException;
use Polkurier\Webservice\Exception\MalformedResponseException;
use Polkurier\Webservice\Exception\ValidationException;
use Polkurier\Webservice\Exception\WebserviceException;
use Polkurier\Webservice\Validator\DefaultValidatorRegistryFactory;
use Polkurier\Webservice\Validator\Violation;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(ErrorMessageResolver::class)]
class ErrorMessageResolverTest extends TestCase
{
    public function testFieldsAreNamedAsOnTheShipmentScreen(): void
    {
        $messages = new ErrorMessageResolver();

        self::assertSame('Recipient: Phone', $messages->getFieldLabel('recipient.phone'));
        self::assertSame('Sender: House number', $messages->getFieldLabel('sender.housenumber'));
        self::assertSame('Parcel 2: Weight', $messages->getFieldLabel('packs.1.weight'));
        self::assertSame('Parcel 2: Weight', $messages->getFieldLabel('packs[1].weight'));
        self::assertSame('Sender on the label: Flat number', $messages->getFieldLabel('cover_address.flat_number'));
        self::assertSame('Cash on delivery: Amount', $messages->getFieldLabel('COD.codamount'));
        self::assertSame('Shipment type', $messages->getFieldLabel('shipmenttype'));
        self::assertSame('Carrier field: locker_size', $messages->getFieldLabel('additional_fields.locker_size'));
        self::assertSame('Shipment description', $messages->getFieldLabel('description'));
        self::assertSame(
            'something.else',
            $messages->getFieldLabel('something.else'),
            'One it doesn\'t know stays as it is.',
        );
    }

    public function testTheSdksOwnValidationNamesTheFieldsAsTheScreenDoes(): void
    {
        $order = new Order(
            'DPD',
            ShipmentTypeEnum::Box,
            [new PackItem(10, 10, 10, 0.0, PackTypeEnum::Standard, 1)],
            new Address('Jan Nadawca', 'Prosta', 'Warszawa', '00-838', 'jan@example.com', '600100200', null, '1'),
            new Address('Anna Odbiorca', 'Długa', 'Kraków', '31-147', 'anna@example.com', '600300400', null, '2'),
            description: 'Zam. 000000042',
        );
        try {
            DefaultValidatorRegistryFactory::create()->validate(new CreateOrderMessage($order));
            self::fail('A parcel weighing nothing is refused.');
        } catch (ValidationException $e) {
            self::assertStringContainsString(
                'Parcel 1: Weight',
                (string)(new ErrorMessageResolver())->createPhrase($e),
            );
        }
    }

    public function testTheSdksReasonsAreWordedByTheModule(): void
    {
        $day = new \DateTimeImmutable('2026-11-20');
        $order = new Order(
            'DPD',
            ShipmentTypeEnum::Box,
            [new PackItem(10, 10, 10, 0.0, PackTypeEnum::Standard, 1)],
            new Address('Jan Nadawca', 'Prosta', 'Warszawa', '00838', 'jan', '12', null, '1'),
            new Address('Anna Odbiorca', 'Długa', 'Kraków', '31-147', 'anna@example.com', '600300400', null, '2'),
            description: 'Zam. 000000042',
            cod: new Cod(100.0),
            pickup: new Pickup($day, $day->setTime(15, 0), $day->setTime(10, 0)),
        );
        try {
            DefaultValidatorRegistryFactory::create()->validate(new CreateOrderMessage($order));
            self::fail('The order is refused.');
        } catch (ValidationException $e) {
            $phrase = (string)(new ErrorMessageResolver())->createPhrase($e);
        }

        foreach ([
            'Parcel 1: Weight must be more than 0',
            'Sender: E-mail must be an e-mail address',
            'Sender: Phone has too few digits (at least 4)',
            'Sender: Postcode must be a postcode of PL',
            'Cash on delivery: Bank account is required for the chosen Cash on delivery: Paid out to',
            'Collection: From must not exceed Collection: To',
        ] as $reason) {
            self::assertStringContainsString($reason, $phrase);
        }
    }

    /**
     * @param array<string, scalar> $parameters
     */
    #[DataProvider('provideWordedViolations')]
    public function testEachShapeOfAReasonIsWorded(
        ValidationErrorCodeEnum $code,
        array $parameters,
        string $expected,
    ): void {
        $phrase = (new ErrorMessageResolver())->createPhrase(new ValidationException(
            ['packs[0].weight' => ['English']],
            violations: [new Violation('packs[0].weight', $code, 'English', $parameters)],
        ));

        self::assertSame('Polkurier would not accept this. Check: Parcel 1: Weight ' . $expected, (string)$phrase);
    }

    /**
     * @return array<string, array{ValidationErrorCodeEnum, array<string, scalar>, string}>
     */
    public static function provideWordedViolations(): array
    {
        $range = ValidationErrorCodeEnum::OutOfRange;
        $blank = ValidationErrorCodeEnum::Blank;

        return [
            'both bounds' => [$range, ['minimum' => 1, 'maximum' => 100], 'must be from 1 to 100'],
            'above a bound' => [$range, ['minimum' => 0, 'exclusiveMinimum' => true], 'must be more than 0'],
            'above one, up to another' => [
                $range,
                ['minimum' => 0, 'maximum' => 5, 'exclusiveMinimum' => true],
                'must be more than 0 and at most 5',
            ],
            'from a bound' => [$range, ['minimum' => 0], 'must be at least 0'],
            'up to a bound' => [$range, ['maximum' => 30], 'must be at most 30'],
            'no bound' => [$range, [], 'English'],
            'always required' => [$blank, [], 'must not be empty'],
            'required with another' => [
                $blank,
                ['field' => 'type', 'condition' => 'set'],
                'is required when Parcel 1: Type is filled in',
            ],
            'required without another' => [
                $blank,
                ['field' => 'type', 'condition' => 'not_set'],
                'is required when Parcel 1: Type is not set',
            ],
            'required when another is empty' => [
                $blank,
                ['field' => 'type', 'condition' => 'empty'],
                'is required when Parcel 1: Type is empty',
            ],
            'required by a value' => [
                $blank,
                ['field' => 'type', 'condition' => 'equals', 'equals' => 'PAL'],
                'is required for the chosen Parcel 1: Type',
            ],
        ];
    }

    public function testAReasonWithoutWordsOfItsOwnStaysInEnglish(): void
    {
        $phrase = (new ErrorMessageResolver())->createPhrase(new ValidationException(
            ['recipient.phone' => ['is odd']],
            violations: [new Violation('recipient.phone', ValidationErrorCodeEnum::Other, 'is odd')],
        ));

        self::assertSame('Polkurier would not accept this. Check: Recipient: Phone is odd', (string)$phrase);
    }

    public function testEachKindOfFailureIsSaidInItsOwnWords(): void
    {
        $messages = new ErrorMessageResolver();
        $response = $this->createStub(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(502);

        self::assertSame(
            'Polkurier would not accept this. Check: Recipient: Phone is required; Parcel 1: Weight too heavy',
            (string)$messages->createPhrase(new ValidationException([
                'recipient.phone' => ['is required'],
                'packs.0.weight' => ['too heavy'],
            ])),
        );
        self::assertSame(
            'The request to Polkurier could not be built: No carrier',
            (string)$messages->createPhrase(new InvalidArgumentException('No carrier')),
        );
        self::assertStringStartsWith(
            'Polkurier did not accept the API credentials.',
            (string)$messages->createPhrase($this->createRefusal(AuthorizationException::class, 'Błędny token')),
        );
        self::assertStringEndsWith(
            '(Błędny token)',
            (string)$messages->createPhrase($this->createRefusal(AuthorizationException::class, 'Błędny token')),
        );
        self::assertSame(
            'Polkurier refused: Kod pocztowy jest nieprawidłowy Check: Recipient: Postcode wrong',
            (string)$messages->createPhrase($this->createRefusal(
                WebserviceException::class,
                'Kod pocztowy jest nieprawidłowy',
                ['recipient.postcode' => ['wrong']],
            )),
        );
        self::assertSame(
            'Polkurier could not be reached, or did not answer in time: cURL error 28',
            (string)$messages->createPhrase(
                new ConnectionException('Request failed', 0, new \RuntimeException('cURL error 28')),
            ),
        );
        self::assertSame(
            'Polkurier answered with something unexpected (HTTP 502). Try again later; '
            . 'if it persists, contact Polkurier.',
            (string)$messages->createPhrase(new MalformedResponseException('Not JSON', $response)),
        );
        self::assertSame(
            'Polkurier did not answer a moment ago.',
            (string)$messages->createPhrase(new RecentFailureException('Polkurier did not answer a moment ago.')),
        );
        self::assertSame(
            'Polkurier error: Something else',
            (string)$messages->createPhrase(new PolkurierException('Something else')),
        );
        self::assertSame(
            'Choose a carrier first.',
            (string)$messages->createPhrase(new LocalizedException(__('Choose a carrier first.'))),
        );
    }

    public function testTheExceptionCarriesTheDescribingPhraseNotItsTranslatedText(): void
    {
        $failure = new ConnectionException('Timed out.');

        $exception = (new ErrorMessageResolver())->createException($failure);

        self::assertSame(
            'Polkurier could not be reached, or did not answer in time: Timed out.',
            $exception->getMessage(),
        );
        self::assertSame(
            'Polkurier could not be reached, or did not answer in time: %1',
            $exception->getRawMessage(),
            'Translated once, as a phrase with the failure in its placeholder.',
        );
        self::assertSame(['Timed out.'], $exception->getParameters());
        self::assertSame($failure, $exception->getPrevious());
    }

    public function testAnotherFailureOnlyFillsAPlaceholderAsItIs(): void
    {
        $messages = new ErrorMessageResolver();

        foreach ([new LocalizedException(__('Choose a carrier.')), new \RuntimeException('Disk full')] as $e) {
            $phrase = $messages->createPhrase($e);

            self::assertSame('%1', $phrase->getText(), 'Its text is never looked up in the dictionary.');
            self::assertSame([$e->getMessage()], $phrase->getArguments());
        }
    }

    public function testATextDescribedAlreadyOnlyFillsAPlaceholder(): void
    {
        $phrase = (new ErrorMessageResolver())->createPhrase(
            new RecentFailureException('Polkurier did not answer a moment ago.'),
        );

        self::assertSame('%1', $phrase->getText());
        self::assertSame(['Polkurier did not answer a moment ago.'], $phrase->getArguments());
    }

    /**
     * @return iterable<string, array{0: ?int, 1: ?string, 2: string, 3: bool}>
     */
    public static function provideRefusals(): iterable
    {
        yield 'unknown error' => [1, 'UNKNOWN_ERROR', 'Nieznany błąd. Proszę spróbować ponownie', true];
        yield 'maintenance' => [22, 'MAINTENANCE_MODE', 'PRZERWA TECHNICZNA', true];
        yield 'too many requests' => [429, 'TOO_MANY_REQUESTS', 'Zbyt duża ilość zapytań', true];
        yield 'internal server error' => [500, 'INTERNAL_SERVER_ERROR', '500 Wewnętrzny błąd serwera', true];
        yield 'a status code only' => [22, null, 'PRZERWA TECHNICZNA', true];
        yield 'service unavailable' => [
            23,
            'ADDITIONAL_SERVICE_UNAVAILABLE',
            'Usługa dodatkowa "Dostawa weekendowa" jest niedostępna [WEEK_COLLECTION]',
            false,
        ];
        yield 'validation' => [26, 'VALIDATION_ERROR', 'Kod pocztowy jest nieprawidłowy [recipient.postcode]', false];
        yield 'too big' => [12, 'PARCELS_ARE_TO_BIG', 'Paczki mają za duży rozmiar.', false];
        yield 'a carrier refusing a service' => [0, null, 'Usługa COD nie jest dostępna.', false];
        yield 'a missing configuration' => [
            null,
            null,
            'Konfiguracja dostępu dla serwisu "dpd_api_order_demo" nie istnieje',
            false,
        ];
        yield 'a carrier down' => [0, null, 'Przewoźnik jest chwilowo niedostępny', true];
        yield 'an overloaded carrier' => [
            0,
            null,
            'Wystąpił błąd przetwarzania danych z powodu przeciążenia systemu GEIS. '
            . 'Proszę spróbować ponownie za około 1 minutę',
            true,
        ];
        yield 'the fatal error' => [
            0,
            null,
            'Wystąpił nieoczekiwany błąd podczas tworzenia zlecenia. '
            . 'Prosimy spróbować ponownie lub skontaktować się z biurem obsługi klienta',
            true,
        ];
        yield 'no reason' => [0, null, '', true];
    }

    #[DataProvider('provideRefusals')]
    public function testOnlyAPassingFailureIsWorthTryingAgain(
        ?int $statusCode,
        ?string $errorCode,
        string $reason,
        bool $transient,
    ): void {
        $refusal = new WebserviceException(
            new ErrorMessage(ResponseStatusEnum::Error, $reason, $statusCode, $errorCode),
            $this->createStub(RequestInterface::class),
            $this->createStub(ResponseInterface::class),
        );

        self::assertSame($transient, (new ErrorMessageResolver())->isTransient($refusal));
        self::assertSame(
            $transient,
            (new ErrorMessageResolver())->isTransient((new ErrorMessageResolver())->createException($refusal)),
            'Seen through the merchant-facing exception.',
        );
    }

    public function testAFailedConnectionOrUnreadableAnswerIsWorthTryingAgain(): void
    {
        $errors = new ErrorMessageResolver();

        self::assertTrue($errors->isTransient(new ConnectionException('cURL error 28: Operation timed out')));
        self::assertTrue($errors->isTransient(new MalformedResponseException(
            'Not JSON',
            $this->createStub(ResponseInterface::class),
        )));
    }

    public function testTheModulesOwnRefusalIsNot(): void
    {
        self::assertFalse(
            (new ErrorMessageResolver())->isTransient(new LocalizedException(__('Choose a carrier first.'))),
        );
    }

    /**
     * @param class-string<WebserviceException> $class
     * @param array<string, string[]>|null $fields
     */
    private function createRefusal(string $class, string $reason, ?array $fields = null): WebserviceException
    {
        return new $class(
            new ErrorMessage(ResponseStatusEnum::Error, $reason, 26, null, $fields),
            $this->createStub(RequestInterface::class),
            $this->createStub(ResponseInterface::class),
        );
    }
}
