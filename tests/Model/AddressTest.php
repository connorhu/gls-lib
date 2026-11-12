<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Tests\Model;

use CodeConjure\Gls\Model\Address;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    public function testItSerializesTheRequiredFields(): void
    {
        $address = new Address(name: 'Kiss Anna', street: 'Fő utca', city: 'Budapest', zipCode: '1011', countryIsoCode: 'HU');

        self::assertSame([
            'Name' => 'Kiss Anna',
            'Street' => 'Fő utca',
            'City' => 'Budapest',
            'ZipCode' => '1011',
            'CountryIsoCode' => 'HU',
        ], $address->toArray());
    }

    public function testOptionalFieldsAppearOnlyWhenSet(): void
    {
        $address = new Address(
            name: 'Kiss Anna',
            street: 'Fő utca',
            city: 'Budapest',
            zipCode: '1011',
            countryIsoCode: 'hu',
            houseNumber: '12',
            houseNumberInfo: 'B lház. 3/4',
            contactName: 'Kiss Anna',
            contactPhone: '+36701234567',
            contactEmail: 'anna@example.com',
        );

        self::assertSame([
            'Name' => 'Kiss Anna',
            'Street' => 'Fő utca',
            'City' => 'Budapest',
            'ZipCode' => '1011',
            'CountryIsoCode' => 'HU',
            'HouseNumber' => '12',
            'HouseNumberInfo' => 'B lház. 3/4',
            'ContactName' => 'Kiss Anna',
            'ContactPhone' => '+36701234567',
            'ContactEmail' => 'anna@example.com',
        ], $address->toArray());
    }

    /**
     * A doksi: `HouseNumber` — „ONLY NUMBER"; a 23-as hiba: „The house number
     * cannot be 0". A betűjel és az emelet a `HouseNumberInfo`-ba megy.
     *
     * @return iterable<string, array{string}>
     */
    public static function invalidHouseNumberProvider(): iterable
    {
        yield 'betűjellel' => ['12/A'];
        yield 'nulla' => ['0'];
        yield 'üres' => [''];
        yield 'tartomány' => ['12-14'];
    }

    #[DataProvider('invalidHouseNumberProvider')]
    public function testTheHouseNumberIsDigitsOnlyAndNotZero(string $houseNumber): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Address(name: 'X', street: 'Fő utca', city: 'Budapest', zipCode: '1011', countryIsoCode: 'HU', houseNumber: $houseNumber);
    }

    /** @return iterable<string, array{string, string, string, string, string}> */
    public static function missingRequiredProvider(): iterable
    {
        yield 'név' => [' ', 'Fő utca', 'Budapest', '1011', 'HU'];
        yield 'utca' => ['X', '', 'Budapest', '1011', 'HU'];
        yield 'város' => ['X', 'Fő utca', '', '1011', 'HU'];
        yield 'irányítószám' => ['X', 'Fő utca', 'Budapest', '', 'HU'];
        yield 'ország' => ['X', 'Fő utca', 'Budapest', '1011', 'HUN'];
    }

    #[DataProvider('missingRequiredProvider')]
    public function testRequiredFieldsAreEnforced(string $name, string $street, string $city, string $zip, string $country): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Address(name: $name, street: $street, city: $city, zipCode: $zip, countryIsoCode: $country);
    }

    public function testHasFullContactRequiresAllThreeContactFields(): void
    {
        $partial = new Address(name: 'X', street: 'Fő utca', city: 'Budapest', zipCode: '1011', countryIsoCode: 'HU', contactName: 'X', contactPhone: '+3670');
        $full = new Address(name: 'X', street: 'Fő utca', city: 'Budapest', zipCode: '1011', countryIsoCode: 'HU', contactName: 'X', contactPhone: '+3670', contactEmail: 'x@example.com');

        self::assertFalse($partial->hasFullContact());
        self::assertTrue($full->hasFullContact());
    }
}
