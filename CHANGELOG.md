# Változásnapló

A csomag a [SemVer](https://semver.org/lang/hu/) szerint verziózódik. A `0.x`
sor azt jelenti, hogy a nyilvános API még mozog: egy `0.y.z` → `0.(y+1).0`
váltás törő változást is tartalmazhat, a javító kiadások (`0.y.z` → `0.y.(z+1)`)
viszont visszafelé kompatibilisek.

## [Nem kiadott]

## [0.1.0] – 2026-11-12

Az első kiadás: a MyGLS REST/JSON API kliense a dokumentáció 25.12.11-es
verziója alapján, teszt-hozzáférés nélkül.

### Hozzáadva

- `Client` — kilenc művelet PSR-18 kliensen és PSR-17 factorykon:
  `prepareLabels()`, `getPrintedLabels()`, `printLabels()`, `deleteLabels()`,
  `modifyCod()`, `getParcelList()`, `getParcelStatuses()`,
  `getParcelListStatuses()`, `getDeliveryPoints()`. Rögzített verziójú
  metódusnevek.
- `Credentials` — SHA-512 bájttömb-jelszó, a nyers jelszó megtartása nélkül.
- `Country` (a hét MyGLS-ország domainje) és `Environment` (éles / teszt).
- Kérés-modellek: `Address`, `Parcel`, `Service` (paraméterenként saját
  factory), `ParcelProperty`, `PackageType`, `LabelFormat` — a dokumentált
  szerveroldali szabályok kliensoldali ellenőrzésével.
- Típusos eredmények (`Response\*Result`) a csomagonkénti hibalistával a
  sikeres tételek mellett.
- `GlsApiException`, `GlsAuthException` (`-1` és HTTP 401/403),
  `GlsDuplicateRequestException` (31-es kód).
- Kapuk: PHPUnit, PHPStan level 9, ECS — GitHub Actionsön, PHP 8.4 és 8.5
  mátrixon, plusz egy `--prefer-lowest` tesztfutás.

### Ismert korlátok

- A sikeres válaszok alakja a valódi API-n nincs mérve (l. README).
- A `getDeliveryPoints()` a pontokat nyers tömbként adja, nem típusos
  objektumként.
- `ADR`, `DPV`, `MMP`, `SM1`, `SZL` szolgáltatásnak még nincs factory-ja.

[Nem kiadott]: https://github.com/connorhu/gls-lib/compare/0.1.0...main
[0.1.0]: https://github.com/connorhu/gls-lib/releases/tag/0.1.0
