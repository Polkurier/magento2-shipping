<?php
/**
 * Copyright (c) 2026 Polkurier.pl
 * All Rights Reserved
 */
declare(strict_types=1);

namespace Polkurier\Shipping\Test\Unit\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Phrase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Polkurier\Shipping\Model\ErrorMessages;
use Polkurier\Webservice\Enum\Http\ResponseStatusEnum;
use Polkurier\Webservice\Exception\ConnectionException;
use Polkurier\Webservice\Exception\MalformedResponseException;
use Polkurier\Webservice\Exception\WebserviceException;
use Polkurier\Webservice\Http\Response\ErrorMessage;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

#[CoversClass(ErrorMessages::class)]
class ErrorMessagesTest extends TestCase
{
    public function testFieldsAreNamedAsOnTheShipmentScreen(): void
    {
        $messages = new ErrorMessages();

        self::assertSame('Recipient: Phone', $messages->fieldLabel('recipient.phone'));
        self::assertSame('Sender: House number', $messages->fieldLabel('sender.housenumber'));
        self::assertSame('Parcel 2: Weight', $messages->fieldLabel('packs.1.weight'));
        self::assertSame('Shipment description', $messages->fieldLabel('description'));
        self::assertSame('something.else', $messages->fieldLabel('something.else'), 'One it doesn\'t know stays as it is.');
    }

    public function testTheExceptionCarriesTheDescribedMessageAsItIs(): void
    {
        $failure = new ConnectionException('Timed out.');

        $exception = (new ErrorMessages())->exception($failure);

        self::assertSame('Polkurier could not be reached, or did not answer in time: Timed out.', $exception->getMessage());
        self::assertSame('Polkurier could not be reached, or did not answer in time: Timed out.', $exception->getRawMessage(), 'No "%1" placeholder to translate.');
        self::assertSame($failure, $exception->getPrevious());
    }

    /**
     * @return iterable<string, array{0: ?int, 1: ?string, 2: string, 3: bool}>
     */
    public static function refusals(): iterable
    {
        yield 'unknown error' => [1, 'UNKNOWN_ERROR', 'Nieznany błąd. Proszę spróbować ponownie', true];
        yield 'maintenance' => [22, 'MAINTENANCE_MODE', 'PRZERWA TECHNICZNA', true];
        yield 'too many requests' => [429, 'TOO_MANY_REQUESTS', 'Zbyt duża ilość zapytań', true];
        yield 'internal server error' => [500, 'INTERNAL_SERVER_ERROR', '500 Wewnętrzny błąd serwera', true];
        yield 'a status code only' => [22, null, 'PRZERWA TECHNICZNA', true];
        yield 'service unavailable' => [23, 'ADDITIONAL_SERVICE_UNAVAILABLE', 'Usługa dodatkowa "Dostawa weekendowa" jest niedostępna [WEEK_COLLECTION]', false];
        yield 'validation' => [26, 'VALIDATION_ERROR', 'Kod pocztowy jest nieprawidłowy [recipient.postcode]', false];
        yield 'too big' => [12, 'PARCELS_ARE_TO_BIG', 'Paczki mają za duży rozmiar.', false];
        yield 'a carrier refusing a service' => [0, null, 'Usługa COD nie jest dostępna.', false];
        yield 'a missing configuration' => [null, null, 'Konfiguracja dostępu dla serwisu "dpd_api_order_demo" nie istnieje', false];
        yield 'a carrier down' => [0, null, 'Przewoźnik jest chwilowo niedostępny', true];
        yield 'an overloaded carrier' => [0, null, 'Wystąpił błąd przetwarzania danych z powodu przeciążenia systemu GEIS. Proszę spróbować ponownie za około 1 minutę', true];
        yield 'the fatal error' => [0, null, 'Wystąpił nieoczekiwany błąd podczas tworzenia zlecenia. Prosimy spróbować ponownie lub skontaktować się z biurem obsługi klienta', true];
        yield 'no reason' => [0, null, '', true];
    }

    #[DataProvider('refusals')]
    public function testOnlyAPassingFailureIsWorthTryingAgain(?int $statusCode, ?string $errorCode, string $reason, bool $transient): void
    {
        $refusal = new WebserviceException(
            new ErrorMessage(ResponseStatusEnum::Error, $reason, $statusCode, $errorCode),
            $this->createStub(RequestInterface::class),
            $this->createStub(ResponseInterface::class),
        );

        self::assertSame($transient, (new ErrorMessages())->isTransient($refusal));
        self::assertSame($transient, (new ErrorMessages())->isTransient((new ErrorMessages())->exception($refusal)), 'Seen through the merchant-facing exception.');
    }

    public function testAFailedConnectionOrUnreadableAnswerIsWorthTryingAgain(): void
    {
        $errors = new ErrorMessages();

        self::assertTrue($errors->isTransient(new ConnectionException('cURL error 28: Operation timed out')));
        self::assertTrue($errors->isTransient(new MalformedResponseException(
            'Not JSON',
            $this->createStub(ResponseInterface::class),
        )));
    }

    public function testTheModulesOwnRefusalIsNot(): void
    {
        self::assertFalse((new ErrorMessages())->isTransient(new LocalizedException(new Phrase('Choose a carrier first.'))));
    }
}
