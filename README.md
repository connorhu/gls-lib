# codeconjure/gls

[![CI](https://github.com/connorhu/gls-lib/actions/workflows/ci.yaml/badge.svg)](https://github.com/connorhu/gls-lib/actions/workflows/ci.yaml)

[MyGLS API](https://api.mygls.hu/docs/MyGLS_API.pdf) protokoll-kliens PHP-hez.
Keretrendszer-független: PSR-18 HTTP klienst és PSR-17 factorykat vár, semmi
mást. A REST/JSON felületet beszéli (a SOAP-ot nem), mind a hét MyGLS-ország
domainjén, éles és teszt környezetben.

Ez a csomag **nem** tartalmaz Sylius- vagy Symfony-integrációt.

## Telepítés

```bash
composer require codeconjure/gls
```

## Használat

```php
use CodeConjure\Gls\Client;
use CodeConjure\Gls\Country;
use CodeConjure\Gls\Credentials;
use CodeConjure\Gls\Environment;
use CodeConjure\Gls\LabelFormat;
use CodeConjure\Gls\Model\Address;
use CodeConjure\Gls\Model\Parcel;
use CodeConjure\Gls\Model\Service;

$gls = new Client(
    $psr18Client,
    $psr17RequestFactory,
    $psr17StreamFactory,
    new Credentials('api@example.com', $password, clientNumber: 100000001, webshopEngine: 'Sylius'),
    Country::Hungary,
    Environment::Test,
);

$recipient = new Address(
    name: 'Kiss Anna',
    street: 'Kossuth tér',
    houseNumber: '5',          // CSAK szám; a betűjel a houseNumberInfo-ba
    city: 'Pécs',
    zipCode: '7621',
    countryIsoCode: 'HU',
    contactName: 'Kiss Anna',
    contactPhone: '+36701234567',
    contactEmail: 'anna@example.com',
);

$prepared = $gls->prepareLabels([
    new Parcel(
        clientNumber: 100000001,
        pickupAddress: $sender,
        deliveryAddress: $recipient,
        clientReference: '000123',
        codAmount: 12990,
        codReference: '000123',
        services: [Service::cod(), Service::psd('2351-CSOMAGPONT')],
    ),
]);

foreach ($prepared->errorsFor('000123') as $error) {
    // csomagonkénti hiba — a többi csomag ettől még átment
}

$labels = $gls->getPrintedLabels(
    array_map(fn ($p) => $p->parcelId, $prepared->parcels),
    LabelFormat::A4_2x2,
    printPosition: 1,
);

file_put_contents('labels.pdf', $labels->pdf);
```

Műveletek: `prepareLabels`, `getPrintedLabels`, `printLabels` (a kettő egy
lépésben), `deleteLabels`, `modifyCod`, `getParcelList`, `getParcelStatuses`
(POD-dal), `getParcelListStatuses` (tömeges, max. 100), `getDeliveryPoints`.

## Hibamodell

| Helyzet | Mit kapsz |
|---|---|
| kapcsolódási hiba, nem 2xx HTTP, nem JSON-objektum válasz | `GlsApiException` |
| a válasz **gyökerében** álló hibakód (pl. a deszerializálási `1000`) | `GlsApiException`, a kód a `getCode()` |
| HTTP 401/403, vagy `-1`-es kód (rossz belépés, zárolt fiók) | `GlsAuthException` |
| 31-es kód: „Same request sent 5 times within last 5 minutes" | `GlsDuplicateRequestException` |
| minden más, csomagonkénti hiba | az eredmény `errors` listája, a sikeres tételek **mellett** |

A MyGLS HTTP 200 mellett ad részleges sikert, ezért a csomagszintű hiba nem
kivétel. A kliens **nem próbálkozik újra**: a 31-es hibát éppen a vak retry
váltja ki, az ismétlés politikája a hívóé.

## Protokoll-csapdák, amiket a kliens kezel

- **Jelszó:** SHA-512 digest **bájtjai**, egész számok JSON-tömbjeként — nem
  hex-string. A `Credentials` a konstruktorban hashel, a nyers jelszót nem
  tartja meg, és nem szerializálható.
- **`byte[]` mezők** (címke-PDF, POD, csomagpont-adat): egész-tömbök a
  válaszban is (`ByteArray`).
- **Dátum:** `"\/Date(<ms>)\/"` — a perjel escape-elése kötelező, a WCF csak
  így ismeri fel. Az eltolásos alak (`+0200`) dekódolva is UTC
  (`DotNetDate`).
- **Hibakód típusa nem egységes:** hol szám (`-1`), hol string (`"1000"`); az
  `ErrorInfo::$code` mindig `int`.
- **Darabszám-korlátok** küldés előtt: `getPrintedLabels` 99,
  `deleteLabels` 50, `getParcelListStatuses` 100, `Parcel::$count` 1–99.
- **A dokumentált szerveroldali szabályok** a `Parcel`/`Address`
  konstruktorában: házszám csak szám és nem 0 (23), INS mellett `count = 1`
  (28), FSS csak FDS-sel (30), PSD-hez teljes címzett-kontakt.
- **Rögzített verziójú metódusnevek** (`PrepareLabels_20190201`,
  `GetPrintedLabels_20251022`, …), hogy egy GLS-oldali „latest" váltás ne
  törjön csendben. A `GetParcelListStatuses`-nek és a `GetDeliveryPoints`-nak
  mérve nincs verziózott neve — ezek verziózatlanul mennek.

## Ami a valódi API-n még nincs mérve

A kliens teszt-hozzáférés nélkül, a dokumentációból és mockolt válaszokból
készült. A hitelesítés alakja (bájttömb-jelszó), a `-1`-es hibakód és a
metódusnevek léte a teszt-API-n mérve van; a sikeres válaszok alakja nincs.
Hozzáférés után ellenőrizendő:

- a sikeres `PrepareLabels` → `GetPrintedLabels` lánc, és a PDF valóban PDF;
- a `GetDeliveryPoints` hitelesítési igénye és a `Data` tömörítése (a
  teszt-API 2026-11-12-én 502-t adott rá);
- a `ParcelProperty` mértékegységei (a doksi mintája cm/kg-ra utal);
- a `PSD` paraméter alakja a GLS térkép-widget pontazonosítójával.

## Verziózás

[SemVer](https://semver.org/lang/hu/); a kiadások a [CHANGELOG.md](CHANGELOG.md)-ben.
A `0.x` soron a nyilvános API még mozoghat: `^0.1`-re érdemes constraintet
írni, `dev-main`-re nem.
