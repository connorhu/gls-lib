<?php

declare(strict_types=1);

namespace CodeConjure\Gls;

use CodeConjure\Gls\Codec\ByteArray;
use CodeConjure\Gls\Codec\DotNetDate;
use CodeConjure\Gls\Exception\GlsApiException;
use CodeConjure\Gls\Exception\GlsAuthException;
use CodeConjure\Gls\Exception\GlsDuplicateRequestException;
use CodeConjure\Gls\Internal\Payload;
use CodeConjure\Gls\Model\Parcel;
use CodeConjure\Gls\Response\DeletedParcel;
use CodeConjure\Gls\Response\DeleteLabelsResult;
use CodeConjure\Gls\Response\DeliveryPointsResult;
use CodeConjure\Gls\Response\ErrorInfo;
use CodeConjure\Gls\Response\ModifyCodResult;
use CodeConjure\Gls\Response\ParcelInfo;
use CodeConjure\Gls\Response\ParcelListResult;
use CodeConjure\Gls\Response\ParcelListStatusesResult;
use CodeConjure\Gls\Response\ParcelStatus;
use CodeConjure\Gls\Response\ParcelStatuses;
use CodeConjure\Gls\Response\ParcelStatusResult;
use CodeConjure\Gls\Response\PrepareLabelsResult;
use CodeConjure\Gls\Response\PrintDataInfo;
use CodeConjure\Gls\Response\PrintedLabelsResult;
use CodeConjure\Gls\Response\PrintLabelsInfo;
use CodeConjure\Gls\Response\PrintLabelsResult;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * A MyGLS REST/JSON API kliense.
 *
 * Hibamodell:
 *
 * - **kivétel** (`GlsApiException`), ha a kérés EGÉSZE elbukott: kapcsolódási
 *   hiba, nem 2xx HTTP, értelmezhetetlen válasz, a válasz gyökerében álló
 *   hibakód (pl. a deszerializálási `1000`);
 * - **`GlsAuthException`**, ha a hitelesítés bukott (HTTP 401/403 vagy a
 *   `-1`-es kód bármelyik hibalistában);
 * - **`GlsDuplicateRequestException`** a 31-es kódra — ezt az újrapróbálkozó
 *   logikának KÖTELEZŐ felismernie;
 * - minden más hiba a művelet **eredményében** jön (`Result::$errors`), a
 *   sikeres tételek mellett: a MyGLS részleges sikert ad HTTP 200-zal.
 *
 * A kliens nem próbálkozik újra: az ismétlés politikája (mikor, hányszor, és
 * hogy a 31-est elkerülje) a hívó dolga.
 *
 * @see https://api.mygls.hu/docs/MyGLS_API.pdf
 */
final class Client
{
    /**
     * Rögzített verziójú metódusnevek. Mérve 2026-11-12-én a teszt-API-n
     * (404 = nem létező metódus): a `GetParcelListStatuses` és a
     * `GetDeliveryPoints` mellé nincs `_20190201` változat, ezek
     * verziózatlanul mennek.
     */
    public const string METHOD_PREPARE_LABELS = 'PrepareLabels_20190201';

    public const string METHOD_GET_PRINTED_LABELS = 'GetPrintedLabels_20251022';

    public const string METHOD_PRINT_LABELS = 'PrintLabels_20251022';

    public const string METHOD_DELETE_LABELS = 'DeleteLabels_20190201';

    public const string METHOD_MODIFY_COD = 'ModifyCOD_20190201';

    public const string METHOD_GET_PARCEL_LIST = 'GetParcelList_20190201';

    public const string METHOD_GET_PARCEL_STATUSES = 'GetParcelStatuses_20190201';

    public const string METHOD_GET_PARCEL_LIST_STATUSES = 'GetParcelListStatuses';

    public const string METHOD_GET_DELIVERY_POINTS = 'GetDeliveryPoints';

    public const int MAX_PRINTED_LABELS = 99;

    public const int MAX_DELETED_LABELS = 50;

    public const int MAX_STATUS_QUERIES = 100;

    /** A státuszlekérdezés nyelvei (ISO 639-1; az `EN` az alapértelmezés szerveroldalon). */
    private const array LANGUAGES = ['EN', 'HR', 'CS', 'HU', 'RO', 'SK', 'SL'];

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly Credentials $credentials,
        private readonly Country $country = Country::Hungary,
        private readonly Environment $environment = Environment::Production,
    ) {
    }

    public function credentials(): Credentials
    {
        return $this->credentials;
    }

    public function country(): Country
    {
        return $this->country;
    }

    public function environment(): Environment
    {
        return $this->environment;
    }

    /**
     * Csomagadatok ellenőrzése és rögzítése — még NEM ad csomagszámot és
     * címkét (az a `getPrintedLabels()`).
     *
     * @param list<Parcel> $parcels
     *
     * @throws GlsApiException
     */
    public function prepareLabels(array $parcels): PrepareLabelsResult
    {
        $response = $this->parcelService(self::METHOD_PREPARE_LABELS, [
            'ParcelList' => $this->parcelList($parcels),
        ], 'PrepareLabelsError');

        return new PrepareLabelsResult(
            array_map(ParcelInfo::fromPayload(...), $response->objects('ParcelInfoList')),
            $this->errors($response, 'PrepareLabelsError'),
        );
    }

    /**
     * Csomagszám és címke-PDF az előkészített csomagokhoz.
     *
     * @param list<int> $parcelIds a `prepareLabels()` által adott azonosítók (max. 99)
     * @param int $printPosition A4-en az első használt negyed (1–4)
     *
     * @throws GlsApiException
     */
    public function getPrintedLabels(
        array $parcelIds,
        LabelFormat $format = LabelFormat::A4_2x2,
        int $printPosition = 1,
        bool $showPrintDialog = false,
        ?bool $hidePhoneNumber = null,
    ): PrintedLabelsResult {
        $this->assertIdList($parcelIds, self::MAX_PRINTED_LABELS, 'csomagazonosító');

        $response = $this->parcelService(self::METHOD_GET_PRINTED_LABELS, [
            'ParcelIdList' => $parcelIds,
            ...$this->printOptions($format, $printPosition, $showPrintDialog, $hidePhoneNumber),
        ], 'GetPrintedLabelsErrorList');

        return new PrintedLabelsResult(
            ByteArray::decodeNullable($response->get('Labels')),
            array_map(PrintDataInfo::fromPayload(...), $response->objects('PrintDataInfoList')),
            $this->errors($response, 'GetPrintedLabelsErrorList'),
        );
    }

    /**
     * Előkészítés és címke egy lépésben.
     *
     * @param list<Parcel> $parcels
     *
     * @throws GlsApiException
     */
    public function printLabels(
        array $parcels,
        LabelFormat $format = LabelFormat::A4_2x2,
        int $printPosition = 1,
        bool $showPrintDialog = false,
        ?bool $hidePhoneNumber = null,
    ): PrintLabelsResult {
        $response = $this->parcelService(self::METHOD_PRINT_LABELS, [
            'ParcelList' => $this->parcelList($parcels),
            ...$this->printOptions($format, $printPosition, $showPrintDialog, $hidePhoneNumber),
        ], 'PrintLabelsErrorList');

        return new PrintLabelsResult(
            ByteArray::decodeNullable($response->get('Labels')),
            array_map(PrintLabelsInfo::fromPayload(...), $response->objects('PrintLabelsInfoList')),
            $this->errors($response, 'PrintLabelsErrorList'),
        );
    }

    /**
     * Sztornó: a csomagok DELETED állapotba kerülnek.
     *
     * @param list<int> $parcelIds max. 50
     *
     * @throws GlsApiException
     */
    public function deleteLabels(array $parcelIds): DeleteLabelsResult
    {
        $this->assertIdList($parcelIds, self::MAX_DELETED_LABELS, 'csomagazonosító');

        $response = $this->parcelService(self::METHOD_DELETE_LABELS, [
            'ParcelIdList' => $parcelIds,
        ], 'DeleteLabelsErrorList');

        return new DeleteLabelsResult(
            array_map(DeletedParcel::fromPayload(...), $response->objects('SuccessfullyDeletedList')),
            $this->errors($response, 'DeleteLabelsErrorList'),
        );
    }

    /**
     * Az utánvét összegének módosítása. Pontosan az egyik azonosító kell.
     *
     * @throws \InvalidArgumentException hiányzó/kettős azonosító vagy negatív összeg
     * @throws GlsApiException
     */
    public function modifyCod(int|float $amount, ?int $parcelId = null, ?int $parcelNumber = null): ModifyCodResult
    {
        if (($parcelId === null) === ($parcelNumber === null)) {
            throw new \InvalidArgumentException('A ModifyCOD-hoz pontosan az egyik kell: parcelId vagy parcelNumber.');
        }

        if ($amount < 0) {
            throw new \InvalidArgumentException('Az utánvét összege nem lehet negatív (8-as hiba).');
        }

        $payload = ['CODAmount' => $amount];

        if ($parcelId !== null) {
            $payload['ParcelId'] = $parcelId;
        }

        if ($parcelNumber !== null) {
            $payload['ParcelNumber'] = $parcelNumber;
        }

        $response = $this->parcelService(self::METHOD_MODIFY_COD, $payload, 'ModifyCODError');

        return new ModifyCodResult($response->bool('Successful'), $this->errors($response, 'ModifyCODError'));
    }

    /**
     * Csomagok dátumtartomány szerint (nyomtatási és/vagy felvételi dátum).
     *
     * @throws GlsApiException
     */
    public function getParcelList(
        ?\DateTimeInterface $printDateFrom = null,
        ?\DateTimeInterface $printDateTo = null,
        ?\DateTimeInterface $pickupDateFrom = null,
        ?\DateTimeInterface $pickupDateTo = null,
    ): ParcelListResult {
        $dates = array_filter([
            'PrintDateFrom' => $printDateFrom,
            'PrintDateTo' => $printDateTo,
            'PickupDateFrom' => $pickupDateFrom,
            'PickupDateTo' => $pickupDateTo,
        ]);

        if ($dates === []) {
            throw new \InvalidArgumentException('A GetParcelList-hez legalább egy dátumtartomány kell.');
        }

        $response = $this->parcelService(
            self::METHOD_GET_PARCEL_LIST,
            array_map(DotNetDate::encode(...), $dates),
            'GetParcelListErrors',
        );

        return new ParcelListResult(
            array_map(PrintDataInfo::fromPayload(...), $response->objects('PrintDataInfoList')),
            $this->errors($response, 'GetParcelListErrors'),
        );
    }

    /**
     * Egy csomag státusztörténete, kérésre a kézbesítési igazolással (POD).
     *
     * @throws GlsApiException
     */
    public function getParcelStatuses(int $parcelNumber, bool $returnPod = false, string $language = 'HU'): ParcelStatusResult
    {
        $response = $this->parcelService(self::METHOD_GET_PARCEL_STATUSES, [
            'ParcelNumber' => $parcelNumber,
            'ReturnPOD' => $returnPod,
            'LanguageIsoCode' => $this->language($language),
        ], 'GetParcelStatusErrors');

        return new ParcelStatusResult(
            $response->nullableInt('ParcelNumber'),
            $response->nullableString('ClientReference'),
            $response->nullableString('DeliveryCountryCode'),
            $response->nullableString('DeliveryZipCode'),
            $response->nullableFloat('Weight'),
            array_map(ParcelStatus::fromPayload(...), $response->objects('ParcelStatusList')),
            ByteArray::decodeNullable($response->get('POD')),
            $this->errors($response, 'GetParcelStatusErrors'),
        );
    }

    /**
     * Több csomag státusztörténete egyszerre — a nyomkövetés-szinkronhoz.
     *
     * @param list<int> $parcelNumbers max. 100
     *
     * @throws GlsApiException
     */
    public function getParcelListStatuses(array $parcelNumbers, string $language = 'HU'): ParcelListStatusesResult
    {
        $this->assertIdList($parcelNumbers, self::MAX_STATUS_QUERIES, 'csomagszám');

        $response = $this->parcelService(self::METHOD_GET_PARCEL_LIST_STATUSES, [
            'ParcelNumberList' => $parcelNumbers,
            'LanguageIsoCode' => $this->language($language),
        ], 'GetParcelListStatusesErrors');

        return new ParcelListStatusesResult(
            array_map(ParcelStatuses::fromPayload(...), $response->objects('ParcelList')),
            $this->errors($response, 'GetParcelListStatusesErrors'),
        );
    }

    /**
     * A csomagpontok és automaták listája (GZIP-elt JSON a `Data` mezőben).
     *
     * A `lastUpdateTime`-mal inkrementális: ha a szerveren nincs újabb, a
     * válasz üres, és a helyi cache marad érvényben.
     *
     * Ez a MasterDataService, aminek a hitelesítési igénye a dokumentációból
     * nem derül ki; a kliens a ParcelService-szel azonos alapot küldi.
     *
     * @throws GlsApiException
     */
    public function getDeliveryPoints(Country $country, ?\DateTimeInterface $lastUpdateTime = null): DeliveryPointsResult
    {
        $payload = ['CountryIsoCode' => $country->value];

        if ($lastUpdateTime !== null) {
            $payload['LastUpdateTime'] = DotNetDate::encode($lastUpdateTime);
        }

        $response = $this->call('MasterDataService', self::METHOD_GET_DELIVERY_POINTS, $payload, []);

        $points = [];
        $data = ByteArray::decodeNullable($response->get('Data'));

        if ($data !== null) {
            $json = @gzdecode($data);

            if ($json === false) {
                throw new GlsApiException('A MyGLS csomagpont-lista nem érvényes GZIP.');
            }

            $decoded = json_decode($json, true);

            if (!\is_array($decoded) || !array_is_list($decoded)) {
                throw new GlsApiException('A MyGLS csomagpont-lista nem JSON-lista.');
            }

            foreach ($decoded as $point) {
                if (!\is_array($point)) {
                    throw new GlsApiException('A MyGLS csomagpont-listában nem objektum áll.');
                }

                $points[] = $point;
            }
        }

        return new DeliveryPointsResult($response->bool('IsChanged'), $response->nullableDate('LastUpdateTime'), $points);
    }

    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $payload
     *
     * @throws GlsApiException
     */
    private function parcelService(string $method, array $payload, string $errorListKey): Payload
    {
        return $this->call('ParcelService', $method, $payload, [$errorListKey]);
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $errorListKeys
     *
     * @throws GlsApiException
     */
    private function call(string $service, string $method, array $payload, array $errorListKeys): Payload
    {
        $url = \sprintf('%s/%s.svc/json/%s', $this->country->apiBaseUrl($this->environment), $service, $method);

        // A JSON_UNESCAPED_SLASHES itt TILOS: a WCF csak a `"\/Date(…)\/"`
        // alakot ismeri fel dátumként (l. DotNetDate).
        $body = json_encode(
            [...$this->credentials->toRequestBase(), ...$payload],
            \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR,
        );

        $request = $this->requestFactory->createRequest('POST', $url)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new GlsApiException(\sprintf('MyGLS kapcsolódási hiba (%s): %s', $method, $e->getMessage()), 0, $e);
        }

        $status = $response->getStatusCode();

        if ($status === 401 || $status === 403) {
            throw new GlsAuthException(\sprintf('MyGLS hitelesítési hiba (%s, HTTP %d).', $method, $status), $status);
        }

        if ($status >= 300 && $status < 400) {
            throw new GlsApiException(\sprintf(
                'MyGLS váratlan átirányítás (%s, HTTP %d): %s',
                $method,
                $status,
                $response->getHeaderLine('Location') !== '' ? $response->getHeaderLine('Location') : 'nincs Location',
            ), $status);
        }

        try {
            $content = (string) $response->getBody();
        } catch (\Throwable $e) {
            throw new GlsApiException(\sprintf('MyGLS válasz olvasási hiba (%s): %s', $method, $e->getMessage()), 0, $e);
        }

        if ($status >= 400) {
            throw new GlsApiException(\sprintf('MyGLS hiba (%s, HTTP %d): %s', $method, $status, mb_substr(trim($content), 0, 300)), $status);
        }

        $decoded = json_decode($content, true);

        if (!\is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new GlsApiException(\sprintf('A MyGLS (%s) nem JSON-objektumot adott vissza.', $method));
        }

        $payload = new Payload($decoded);

        $rootCode = $payload->nullableInt('ErrorCode');

        if ($rootCode !== null && $rootCode !== 0) {
            $this->throwFor(new ErrorInfo($rootCode, $payload->string('ErrorDescription')), $method);
        }

        foreach ($errorListKeys as $key) {
            foreach ($this->errors($payload, $key) as $error) {
                if ($error->code === -1 || $error->code === GlsDuplicateRequestException::ERROR_CODE) {
                    $this->throwFor($error, $method);
                }
            }
        }

        return $payload;
    }

    /**
     * @throws GlsApiException
     */
    private function throwFor(ErrorInfo $error, string $method): never
    {
        $message = \sprintf('MyGLS hiba (%s, %d): %s', $method, $error->code, $error->description);

        throw match ($error->code) {
            -1 => new GlsAuthException($message, -1),
            GlsDuplicateRequestException::ERROR_CODE => new GlsDuplicateRequestException($message, $error->code),
            default => new GlsApiException($message, $error->code),
        };
    }

    /** @return list<ErrorInfo> */
    private function errors(Payload $payload, string $key): array
    {
        return array_map(ErrorInfo::fromPayload(...), $payload->objects($key));
    }

    /**
     * @param list<Parcel> $parcels
     *
     * @return list<array<string, mixed>>
     */
    private function parcelList(array $parcels): array
    {
        if ($parcels === []) {
            throw new \InvalidArgumentException('Legalább egy csomag kell (12-es hiba).');
        }

        return array_map(static fn (Parcel $parcel): array => $parcel->toArray(), $parcels);
    }

    /**
     * @return array<string, bool|int|string>
     */
    private function printOptions(LabelFormat $format, int $printPosition, bool $showPrintDialog, ?bool $hidePhoneNumber): array
    {
        $options = ['TypeOfPrinter' => $format->value, 'ShowPrintDialog' => $showPrintDialog];

        if ($format->isA4()) {
            if ($printPosition < 1 || $printPosition > 4) {
                throw new \InvalidArgumentException(\sprintf('A PrintPosition az A4-es lap negyede (1–4), nem %d.', $printPosition));
            }

            $options['PrintPosition'] = $printPosition;
        }

        if ($hidePhoneNumber !== null) {
            $options['HidePhoneNumberOnLabels'] = $hidePhoneNumber;
        }

        return $options;
    }

    /** @param list<int> $ids */
    private function assertIdList(array $ids, int $max, string $label): void
    {
        if ($ids === []) {
            throw new \InvalidArgumentException(\sprintf('Legalább egy %s kell.', $label));
        }

        if (\count($ids) > $max) {
            throw new \InvalidArgumentException(\sprintf('Egy kérésben legfeljebb %d %s mehet, nem %d.', $max, $label, \count($ids)));
        }
    }

    private function language(string $language): string
    {
        $language = strtoupper($language);

        if (!\in_array($language, self::LANGUAGES, true)) {
            throw new \InvalidArgumentException(\sprintf('Nem támogatott nyelvkód: "%s" (%s).', $language, implode(', ', self::LANGUAGES)));
        }

        return $language;
    }
}
