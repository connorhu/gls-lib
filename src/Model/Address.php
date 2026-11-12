<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Model;

/**
 * A MyGLS `Address` osztálya — a feladási és a kézbesítési cím.
 *
 * Az utca és a házszám KÜLÖN mező: a `HouseNumber` csak számjegy lehet (és
 * nem 0 — a 23-as hiba), a betűjel, lépcsőház, emelet a `HouseNumberInfo`-ba
 * megy. Egy egysoros címet a hívónak kell szétbontania.
 */
final readonly class Address
{
    public string $countryIsoCode;

    public function __construct(
        public string $name,
        public string $street,
        public string $city,
        public string $zipCode,
        string $countryIsoCode,
        public ?string $houseNumber = null,
        public ?string $houseNumberInfo = null,
        public ?string $contactName = null,
        public ?string $contactPhone = null,
        public ?string $contactEmail = null,
    ) {
        foreach (['name' => $name, 'street' => $street, 'city' => $city, 'zipCode' => $zipCode] as $field => $value) {
            if (trim($value) === '') {
                throw new \InvalidArgumentException(\sprintf('A cím `%s` mezője kötelező.', $field));
            }
        }

        if (preg_match('/^[A-Za-z]{2}$/', $countryIsoCode) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Az országkód kétbetűs ISO 3166-1 kód, nem "%s".', $countryIsoCode));
        }

        if ($houseNumber !== null && preg_match('/^0*[1-9]\d*$/', $houseNumber) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'A MyGLS házszám csak pozitív szám lehet, nem "%s" — a betűjel és a többi a `houseNumberInfo`-ba megy.',
                $houseNumber,
            ));
        }

        $this->countryIsoCode = strtoupper($countryIsoCode);
    }

    public function hasFullContact(): bool
    {
        foreach ([$this->contactName, $this->contactPhone, $this->contactEmail] as $value) {
            if ($value === null || trim($value) === '') {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'Name' => $this->name,
            'Street' => $this->street,
            'City' => $this->city,
            'ZipCode' => $this->zipCode,
            'CountryIsoCode' => $this->countryIsoCode,
            'HouseNumber' => $this->houseNumber,
            'HouseNumberInfo' => $this->houseNumberInfo,
            'ContactName' => $this->contactName,
            'ContactPhone' => $this->contactPhone,
            'ContactEmail' => $this->contactEmail,
        ], static fn (?string $value): bool => $value !== null);
    }
}
