<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Tests;

use CodeConjure\Gls\Client;
use CodeConjure\Gls\Country;
use CodeConjure\Gls\Credentials;
use CodeConjure\Gls\Environment;
use CodeConjure\Gls\Exception\GlsApiException;
use CodeConjure\Gls\Exception\GlsAuthException;
use CodeConjure\Gls\Exception\GlsDuplicateRequestException;
use CodeConjure\Gls\LabelFormat;
use CodeConjure\Gls\Model\Address;
use CodeConjure\Gls\Model\Parcel;
use CodeConjure\Gls\Model\Service;
use Http\Client\Exception\NetworkException;
use Http\Mock\Client as MockHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Request;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * A MyGLS kliens — hálózat nélkül, PSR-18 teszt-duplával.
 *
 * A válaszminták a dokumentáció (ver. 25.12.11.) osztályleírásaiból és a
 * teszt-API-n 2026-11-12-én mért alakokból készültek. Valódi hozzáférés
 * nélkül ennyit lehet bizonyítani; a mérési lista a bolt #264-es issue-ja.
 */
final class ClientTest extends TestCase
{
    private MockHttpClient $httpClient;

    // -------------------------------------------------------------------------
    // Végpontok és a kérés közös része
    // -------------------------------------------------------------------------

    /**
     * A metódusnév RÖGZÍTETT verziójú, hogy egy GLS-oldali „latest" váltás ne
     * törje csendben a klienst. Kivétel, ahol mérve nincs verziózott név
     * (2026-11-12: `PrepareLabelsV2_20190201` és
     * `GetParcelListStatuses_20190201` → 404).
     *
     * @return iterable<string, array{callable(Client): mixed, string, string}>
     */
    public static function endpointProvider(): iterable
    {
        yield 'PrepareLabels' => [static fn (Client $c) => $c->prepareLabels([self::parcel()]), 'ParcelService', 'PrepareLabels_20190201'];
        yield 'GetPrintedLabels' => [static fn (Client $c) => $c->getPrintedLabels([1]), 'ParcelService', 'GetPrintedLabels_20251022'];
        yield 'PrintLabels' => [static fn (Client $c) => $c->printLabels([self::parcel()]), 'ParcelService', 'PrintLabels_20251022'];
        yield 'DeleteLabels' => [static fn (Client $c) => $c->deleteLabels([1]), 'ParcelService', 'DeleteLabels_20190201'];
        yield 'ModifyCOD' => [static fn (Client $c) => $c->modifyCod(100, parcelId: 1), 'ParcelService', 'ModifyCOD_20190201'];
        yield 'GetParcelStatuses' => [static fn (Client $c) => $c->getParcelStatuses(123), 'ParcelService', 'GetParcelStatuses_20190201'];
        yield 'GetParcelListStatuses' => [static fn (Client $c) => $c->getParcelListStatuses([123]), 'ParcelService', 'GetParcelListStatuses'];
        yield 'GetParcelList' => [
            static fn (Client $c) => $c->getParcelList(printDateFrom: new \DateTimeImmutable('-1 day'), printDateTo: new \DateTimeImmutable()),
            'ParcelService',
            'GetParcelList_20190201',
        ];
        yield 'GetDeliveryPoints' => [static fn (Client $c) => $c->getDeliveryPoints(Country::Hungary), 'MasterDataService', 'GetDeliveryPoints'];
    }

    /** @param callable(Client): mixed $call */
    #[DataProvider('endpointProvider')]
    public function testEveryOperationPostsJsonToItsPinnedMethod(callable $call, string $service, string $method): void
    {
        $call($this->client(new Response(200, [], $service === 'MasterDataService' ? '{"ErrorCode":0,"IsChanged":false,"Data":null}' : '{}')));

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame(\sprintf('https://api.test.mygls.hu/%s.svc/json/%s', $service, $method), (string) $request->getUri());
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
    }

    /** @param callable(Client): mixed $call */
    #[DataProvider('endpointProvider')]
    public function testEveryRequestCarriesTheAuthentication(callable $call, string $service, string $method): void
    {
        $call($this->client(new Response(200, [], $service === 'MasterDataService' ? '{"ErrorCode":0,"IsChanged":false,"Data":null}' : '{}')));

        $body = $this->lastRequestBody();
        self::assertSame('user@example.com', $body['Username'], $method);
        self::assertSame(array_values(unpack('C*', hash('sha512', 'titok', true)) ?: []), $body['Password'], $method);
        self::assertSame('Sylius', $body['WebshopEngine'], $method);
    }

    public function testTheCountryAndEnvironmentSelectTheDomain(): void
    {
        $this->client(new Response(200, [], '{}'));
        $client = $this->makeClient(Country::Czechia, Environment::Production);

        $client->deleteLabels([1]);

        self::assertSame('https://api.mygls.cz/ParcelService.svc/json/DeleteLabels_20190201', (string) $this->lastRequest()->getUri());
    }

    /**
     * A WCF csak az escape-elt `"\/Date(…)\/"` alakot ismeri fel dátumként.
     */
    public function testDatesTravelWithEscapedSlashes(): void
    {
        $this->client(new Response(200, [], '{}'))->getParcelList(
            printDateFrom: new \DateTimeImmutable('2020-08-31T21:59:59Z'),
            printDateTo: new \DateTimeImmutable('2020-09-01T21:59:59Z'),
        );

        $raw = (string) $this->lastRequest()->getBody();
        self::assertStringContainsString('"PrintDateFrom":"\/Date(1598911199000)\/"', $raw);
        self::assertStringContainsString('"PrintDateTo":"\/Date(1598997599000)\/"', $raw);
    }

    // -------------------------------------------------------------------------
    // Hibakezelés
    // -------------------------------------------------------------------------

    /** @return iterable<string, array{int}> */
    public static function authStatusProvider(): iterable
    {
        yield '401' => [401];
        yield '403' => [403];
    }

    #[DataProvider('authStatusProvider')]
    public function testAnHttpAuthErrorIsAnAuthException(int $status): void
    {
        $this->expectException(GlsAuthException::class);

        $this->client(new Response($status))->deleteLabels([1]);
    }

    /**
     * Mérve (2026-11-12): rossz hitelesítésre HTTP 200 jön, a művelet saját
     * hibalistájában `-1`-es kóddal — a zárolt fióknál is.
     *
     * @return iterable<string, array{string}>
     */
    public static function measuredAuthErrorProvider(): iterable
    {
        yield 'Unauthorized' => ['Unauthorized.'];
        yield 'zárolás' => ['Too many failed login attempts. Your account has been locked until 12:34.'];
    }

    #[DataProvider('measuredAuthErrorProvider')]
    public function testErrorCodeMinusOneIsAnAuthException(string $description): void
    {
        $client = $this->client(new Response(200, [], json_encode([
            'DeleteLabelsErrorList' => [['ClientReferenceList' => [], 'ErrorCode' => -1, 'ErrorDescription' => $description, 'ParcelIdList' => []]],
            'SuccessfullyDeletedList' => [],
        ], \JSON_THROW_ON_ERROR)));

        $this->expectException(GlsAuthException::class);
        $this->expectExceptionMessage($description);

        $client->deleteLabels([1]);
    }

    /**
     * A 31-es hiba a retry-csapda: a hívónak fel KELL ismernie, ezért saját
     * kivétel. A felismerés a kódon alapul (az üzenet számai változók).
     */
    public function testErrorThirtyOneIsADuplicateRequestException(): void
    {
        $client = $this->client(new Response(200, [], json_encode([
            'PrepareLabelsError' => [['ClientReferenceList' => [], 'ErrorCode' => 31, 'ErrorDescription' => 'Same request sent 7 times within last 3 minutes', 'ParcelIdList' => []]],
            'ParcelInfoList' => [],
        ], \JSON_THROW_ON_ERROR)));

        try {
            $client->prepareLabels([self::parcel()]);
            self::fail('Kivételt vártunk.');
        } catch (GlsDuplicateRequestException $e) {
            self::assertSame(31, $e->getCode());
        }
    }

    /**
     * Mérve (2026-11-12): a deszerializálási hiba a válasz GYÖKERÉBEN jön, és
     * a kódja STRING (`"1000"`), szemben a `-1` számmal.
     */
    public function testATopLevelErrorRejectsTheWholeRequest(): void
    {
        $client = $this->client(new Response(200, [], '{"ErrorCode":"1000","ErrorDescription":"End element \'Password\' from namespace \'\' expected."}'));

        try {
            $client->deleteLabels([1]);
            self::fail('Kivételt vártunk.');
        } catch (GlsApiException $e) {
            self::assertNotInstanceOf(GlsAuthException::class, $e);
            self::assertSame(1000, $e->getCode());
            self::assertStringContainsString('Password', $e->getMessage());
        }
    }

    public function testAnHttpErrorNamesTheMethod(): void
    {
        $this->expectException(GlsApiException::class);
        $this->expectExceptionMessage('DeleteLabels_20190201');

        $this->client(new Response(404, [], '<html>Endpoint not found.</html>'))->deleteLabels([1]);
    }

    public function testARedirectIsAnError(): void
    {
        $this->expectException(GlsApiException::class);
        $this->expectExceptionMessage('átirányítás');

        $this->client(new Response(302, ['Location' => 'https://example.com']))->deleteLabels([1]);
    }

    public function testATransportFailureIsAnApiException(): void
    {
        $this->httpClient = new MockHttpClient();
        $this->httpClient->addException(new NetworkException('Connection died', new Request('POST', 'https://api.test.mygls.hu')));
        $client = $this->makeClient();

        $this->expectException(GlsApiException::class);
        $this->expectExceptionMessage('Connection died');

        $client->deleteLabels([1]);
    }

    /** @return iterable<string, array{string}> */
    public static function garbageBodyProvider(): iterable
    {
        yield 'HTML' => ['<html></html>'];
        yield 'üres' => [''];
        yield 'JSON lista' => ['[1,2]'];
        yield 'JSON skalár' => ['"ok"'];
    }

    #[DataProvider('garbageBodyProvider')]
    public function testANonObjectBodyIsAnApiException(string $body): void
    {
        $this->expectException(GlsApiException::class);

        $this->client(new Response(200, [], $body))->deleteLabels([1]);
    }

    // -------------------------------------------------------------------------
    // PrepareLabels — részleges siker
    // -------------------------------------------------------------------------

    public function testPrepareLabelsSendsTheParcels(): void
    {
        $this->client(new Response(200, [], '{}'))->prepareLabels([self::parcel('A1'), self::parcel('A2')]);

        $body = $this->lastRequestBody();
        self::assertSame([self::parcel('A1')->toArray(), self::parcel('A2')->toArray()], $body['ParcelList']);
    }

    public function testPrepareLabelsReturnsSuccessesAndFailuresSideBySide(): void
    {
        $client = $this->client(new Response(200, [], json_encode([
            'ParcelInfoList' => [['ClientReference' => 'A1', 'ParcelId' => 1001]],
            'PrepareLabelsError' => [['ClientReferenceList' => ['A2'], 'ErrorCode' => '13', 'ErrorDescription' => 'Parcel validation issue', 'ParcelIdList' => []]],
        ], \JSON_THROW_ON_ERROR)));

        $result = $client->prepareLabels([self::parcel('A1'), self::parcel('A2')]);

        self::assertCount(1, $result->parcels);
        self::assertSame(1001, $result->parcels[0]->parcelId);
        self::assertSame('A1', $result->parcels[0]->clientReference);
        self::assertTrue($result->hasErrors());
        self::assertSame(13, $result->errors[0]->code);
        self::assertSame(['A2'], $result->errors[0]->clientReferences);
        self::assertCount(1, $result->errorsFor('A2'));
        self::assertSame([], $result->errorsFor('A1'));
    }

    public function testAnEmptyParcelListIsRejectedBeforeSending(): void
    {
        $client = $this->client();

        $this->expectException(\InvalidArgumentException::class);

        $client->prepareLabels([]);
    }

    // -------------------------------------------------------------------------
    // Címke
    // -------------------------------------------------------------------------

    public function testGetPrintedLabelsDecodesThePdfAndThePrintData(): void
    {
        $client = $this->client(new Response(200, [], json_encode([
            'GetPrintedLabelsErrorList' => [],
            'Labels' => array_values(unpack('C*', '%PDF-1.4 címke') ?: []),
            'PrintDataInfoList' => [[
                'B2CChar' => 'B',
                'ClientReference' => 'A1',
                'Depot' => '76',
                'DepotNumber' => '0076',
                'Driver' => '123',
                'Parcel' => null,
                'ParcelId' => 1001,
                'ParcelNumber' => 12345678901,
                'ParcelNumberWithCheckdigit' => 123456789012,
                'Sort' => 'S1',
                'Displaylanguage' => 'HU',
                'PIN' => null,
            ]],
        ], \JSON_THROW_ON_ERROR)));

        $result = $client->getPrintedLabels([1001], LabelFormat::A4_4x1, printPosition: 3);

        self::assertSame('%PDF-1.4 címke', $result->pdf);
        self::assertSame(1001, $result->printData[0]->parcelId);
        self::assertSame(12345678901, $result->printData[0]->parcelNumber);
        self::assertSame(123456789012, $result->printData[0]->parcelNumberWithCheckdigit);
        self::assertSame('A1', $result->printData[0]->clientReference);
        self::assertNull($result->printData[0]->pin);

        $body = $this->lastRequestBody();
        self::assertSame([1001], $body['ParcelIdList']);
        self::assertSame('A4_4x1', $body['TypeOfPrinter']);
        self::assertSame(3, $body['PrintPosition']);
        self::assertFalse($body['ShowPrintDialog']);
        self::assertArrayNotHasKey('HidePhoneNumberOnLabels', $body);
    }

    public function testHidePhoneNumberIsSentOnlyWhenAsked(): void
    {
        $this->client(new Response(200, [], '{}'))->getPrintedLabels([1], hidePhoneNumber: true);

        self::assertTrue($this->lastRequestBody()['HidePhoneNumberOnLabels']);
    }

    public function testANullPdfIsNull(): void
    {
        $result = $this->client(new Response(200, [], '{"Labels":null,"GetPrintedLabelsErrorList":[],"PrintDataInfoList":[]}'))->getPrintedLabels([1]);

        self::assertNull($result->pdf);
    }

    public function testAtMostNinetyNineLabelsPerRequest(): void
    {
        $client = $this->client();

        $this->expectException(\InvalidArgumentException::class);

        $client->getPrintedLabels(range(1, 100));
    }

    /** A „PrintPosition … ACCEPTED ONLY FOR A4-FORMAT", 1–4. */
    public function testThePrintPositionIsAQuarterOfAnA4Page(): void
    {
        $client = $this->client();

        $this->expectException(\InvalidArgumentException::class);

        $client->getPrintedLabels([1], LabelFormat::A4_2x2, printPosition: 5);
    }

    public function testThePrintPositionIsNotSentForThermoFormats(): void
    {
        $this->client(new Response(200, [], '{}'))->getPrintedLabels([1], LabelFormat::Thermo);

        self::assertArrayNotHasKey('PrintPosition', $this->lastRequestBody());
    }

    public function testPrintLabelsReturnsParcelNumbersAndPins(): void
    {
        $client = $this->client(new Response(200, [], json_encode([
            'Labels' => [37, 80, 68, 70],
            'PrintLabelsErrorList' => [],
            'PrintLabelsInfoList' => [['ClientReference' => 'A1', 'ParcelId' => 1001, 'ParcelNumber' => 12345678901, 'PIN' => '4711']],
        ], \JSON_THROW_ON_ERROR)));

        $result = $client->printLabels([self::parcel('A1')]);

        self::assertSame('%PDF', $result->pdf);
        self::assertSame(12345678901, $result->labels[0]->parcelNumber);
        self::assertSame('4711', $result->labels[0]->pin);
        self::assertSame('A4_2x2', $this->lastRequestBody()['TypeOfPrinter']);
        self::assertSame(1, $this->lastRequestBody()['PrintPosition']);
    }

    // -------------------------------------------------------------------------
    // Sztornó, utánvét
    // -------------------------------------------------------------------------

    public function testDeleteLabelsReturnsTheDeletedParcelsWithTheirSubParcels(): void
    {
        $client = $this->client(new Response(200, [], json_encode([
            'DeleteLabelsErrorList' => [['ClientReferenceList' => [], 'ErrorCode' => 4, 'ErrorDescription' => 'Parcel ID not exists', 'ParcelIdList' => [9]]],
            'SuccessfullyDeletedList' => [['ParcelId' => 1001, 'SubParcelIdList' => [1002, 1003]]],
        ], \JSON_THROW_ON_ERROR)));

        $result = $client->deleteLabels([1001, 9]);

        self::assertSame(1001, $result->deleted[0]->parcelId);
        self::assertSame([1002, 1003], $result->deleted[0]->subParcelIds);
        self::assertSame([9], $result->errors[0]->parcelIds);
        self::assertSame([1001, 9], $this->lastRequestBody()['ParcelIdList']);
    }

    public function testAtMostFiftyDeletionsPerRequest(): void
    {
        $client = $this->client();

        $this->expectException(\InvalidArgumentException::class);

        $client->deleteLabels(range(1, 51));
    }

    public function testModifyCodByParcelNumber(): void
    {
        $result = $this->client(new Response(200, [], '{"ModifyCODError":[],"Successful":true}'))->modifyCod(12990, parcelNumber: 12345678901);

        self::assertTrue($result->successful);
        $body = $this->lastRequestBody();
        self::assertSame(12990, $body['CODAmount']);
        self::assertSame(12345678901, $body['ParcelNumber']);
        self::assertArrayNotHasKey('ParcelId', $body);
    }

    public function testModifyCodNeedsExactlyOneIdentifier(): void
    {
        $client = $this->client();

        foreach ([[null, null], [1, 2]] as [$parcelId, $parcelNumber]) {
            try {
                $client->modifyCod(100, parcelId: $parcelId, parcelNumber: $parcelNumber);
                self::fail('Kivételt vártunk.');
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testModifyCodRejectsANegativeAmount(): void
    {
        $client = $this->client();

        $this->expectException(\InvalidArgumentException::class);

        $client->modifyCod(-1, parcelId: 1);
    }

    // -------------------------------------------------------------------------
    // Nyomkövetés
    // -------------------------------------------------------------------------

    public function testGetParcelStatusesDecodesTheHistoryAndThePod(): void
    {
        $client = $this->client(new Response(200, [], json_encode([
            'ClientReference' => 'A1',
            'DeliveryCountryCode' => 'HU',
            'DeliveryZipCode' => '7621',
            'GetParcelStatusErrors' => [],
            'ParcelNumber' => 12345678901,
            'ParcelStatusList' => [
                ['DepotCity' => 'Pécs', 'DepotNumber' => '76', 'StatusCode' => '05', 'StatusDate' => '/Date(1598911199000+0200)/', 'StatusDescription' => 'Kézbesítve', 'StatusInfo' => ''],
            ],
            'POD' => [37, 80, 68, 70],
            'Weight' => 1.25,
        ], \JSON_THROW_ON_ERROR)));

        $result = $client->getParcelStatuses(12345678901, returnPod: true, language: 'hu');

        self::assertSame(12345678901, $result->parcelNumber);
        self::assertSame('A1', $result->clientReference);
        self::assertSame('7621', $result->deliveryZipCode);
        self::assertSame(1.25, $result->weight);
        self::assertSame('%PDF', $result->pod);
        self::assertSame('05', $result->statuses[0]->code);
        self::assertSame('2020-08-31T21:59:59+00:00', $result->statuses[0]->date->format(\DATE_ATOM));
        self::assertSame('Kézbesítve', $result->statuses[0]->description);

        $body = $this->lastRequestBody();
        self::assertSame(12345678901, $body['ParcelNumber']);
        self::assertTrue($body['ReturnPOD']);
        self::assertSame('HU', $body['LanguageIsoCode']);
    }

    public function testGetParcelListStatusesGroupsTheHistoryPerParcel(): void
    {
        $client = $this->client(new Response(200, [], json_encode([
            'GetParcelListStatusesErrors' => [],
            'ParcelList' => [
                ['ParcelNumber' => 111, 'Weight' => null, 'ClientNumber' => 100000001, 'ClientReference' => 'A1', 'ParcelStatusList' => [
                    ['DepotCity' => 'Pécs', 'DepotNumber' => '76', 'StatusCode' => '51', 'StatusDate' => '/Date(1598911199000)/', 'StatusDescription' => 'Adat rögzítve', 'StatusInfo' => null],
                ]],
                ['ParcelNumber' => 222, 'Weight' => 2.0, 'ClientNumber' => 100000001, 'ClientReference' => 'A2', 'ParcelStatusList' => []],
            ],
        ], \JSON_THROW_ON_ERROR)));

        $result = $client->getParcelListStatuses([111, 222]);

        self::assertSame(111, $result->parcels[0]->parcelNumber);
        self::assertNull($result->parcels[0]->weight);
        self::assertSame('51', $result->parcels[0]->statuses[0]->code);
        self::assertSame([], $result->parcels[1]->statuses);
        self::assertSame('A2', $result->forParcelNumber(222)?->clientReference);
        self::assertNull($result->forParcelNumber(333));

        self::assertSame([111, 222], $this->lastRequestBody()['ParcelNumberList']);
        self::assertSame('HU', $this->lastRequestBody()['LanguageIsoCode']);
    }

    public function testAtMostOneHundredStatusQueriesPerRequest(): void
    {
        $client = $this->client();

        $this->expectException(\InvalidArgumentException::class);

        $client->getParcelListStatuses(range(1, 101));
    }

    public function testAnUnknownLanguageIsRejected(): void
    {
        $client = $this->client();

        $this->expectException(\InvalidArgumentException::class);

        $client->getParcelListStatuses([1], language: 'DE');
    }

    public function testGetParcelListNeedsAtLeastOneDateRange(): void
    {
        $client = $this->client();

        $this->expectException(\InvalidArgumentException::class);

        $client->getParcelList();
    }

    public function testGetParcelListReturnsThePrintData(): void
    {
        $client = $this->client(new Response(200, [], json_encode([
            'GetParcelListErrors' => [],
            'PrintDataInfoList' => [['ParcelId' => 1001, 'ParcelNumber' => 111, 'ClientReference' => 'A1']],
        ], \JSON_THROW_ON_ERROR)));

        $result = $client->getParcelList(pickupDateFrom: new \DateTimeImmutable('2020-08-31'), pickupDateTo: new \DateTimeImmutable('2020-09-01'));

        self::assertSame(111, $result->printData[0]->parcelNumber);
        self::assertArrayNotHasKey('PrintDateFrom', $this->lastRequestBody());
    }

    // -------------------------------------------------------------------------
    // Csomagpontok
    // -------------------------------------------------------------------------

    public function testGetDeliveryPointsUnzipsTheData(): void
    {
        $points = [['Id' => 2351, 'DeliveryPointType' => 1, 'CodHandler' => true, 'Address' => ['City' => 'Budapest']]];
        $gzip = gzencode(json_encode($points, \JSON_THROW_ON_ERROR));
        self::assertIsString($gzip);

        $client = $this->client(new Response(200, [], json_encode([
            'ErrorCode' => 0,
            'ErrorDescription' => null,
            'IsChanged' => true,
            'LastUpdateTime' => '/Date(1598911199000)/',
            'Data' => array_values(unpack('C*', $gzip) ?: []),
        ], \JSON_THROW_ON_ERROR)));

        $result = $client->getDeliveryPoints(Country::Hungary, new \DateTimeImmutable('2020-08-30T00:00:00Z'));

        self::assertTrue($result->changed);
        self::assertSame($points, $result->points);
        self::assertSame('2020-08-31T21:59:59+00:00', $result->lastUpdateTime?->format(\DATE_ATOM));

        $body = $this->lastRequestBody();
        self::assertSame('HU', $body['CountryIsoCode']);
        self::assertSame('/Date(1598745600000)/', $body['LastUpdateTime']);
    }

    public function testAnUnchangedDeliveryPointListHasNoData(): void
    {
        $result = $this->client(new Response(200, [], '{"ErrorCode":0,"IsChanged":false,"LastUpdateTime":"\/Date(1598911199000)\/","Data":null}'))
            ->getDeliveryPoints(Country::Hungary);

        self::assertFalse($result->changed);
        self::assertSame([], $result->points);
        self::assertArrayNotHasKey('LastUpdateTime', $this->lastRequestBody());
    }

    public function testCorruptDeliveryPointDataIsAnApiException(): void
    {
        $client = $this->client(new Response(200, [], '{"ErrorCode":0,"IsChanged":true,"Data":[1,2,3]}'));

        $this->expectException(GlsApiException::class);

        $client->getDeliveryPoints(Country::Hungary);
    }

    public function testADeliveryPointErrorCodeIsAnApiException(): void
    {
        $client = $this->client(new Response(200, [], '{"ErrorCode":1001,"ErrorDescription":"Internal Problem","IsChanged":false,"Data":null}'));

        $this->expectException(GlsApiException::class);
        $this->expectExceptionCode(1001);

        $client->getDeliveryPoints(Country::Hungary);
    }

    // -------------------------------------------------------------------------

    private function client(Response ...$responses): Client
    {
        $this->httpClient = new MockHttpClient();

        foreach ($responses as $response) {
            $this->httpClient->addResponse($response);
        }

        return $this->makeClient();
    }

    private function makeClient(Country $country = Country::Hungary, Environment $environment = Environment::Test): Client
    {
        $factory = new Psr17Factory();

        return new Client(
            $this->httpClient,
            $factory,
            $factory,
            new Credentials('user@example.com', 'titok', 100000001, 'Sylius'),
            $country,
            $environment,
        );
    }

    private function lastRequest(): RequestInterface
    {
        $request = $this->httpClient->getLastRequest();
        self::assertInstanceOf(RequestInterface::class, $request);

        return $request;
    }

    /** @return array<string, mixed> */
    private function lastRequestBody(): array
    {
        $decoded = json_decode((string) $this->lastRequest()->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        $body = [];

        foreach ($decoded as $key => $value) {
            $body[(string) $key] = $value;
        }

        return $body;
    }

    private static function parcel(string $reference = 'A1'): Parcel
    {
        $address = new Address(name: 'Kiss Anna', street: 'Kossuth tér', city: 'Pécs', zipCode: '7621', countryIsoCode: 'HU', houseNumber: '5');

        return new Parcel(clientNumber: 100000001, pickupAddress: $address, deliveryAddress: $address, clientReference: $reference, services: [Service::cod()]);
    }
}
