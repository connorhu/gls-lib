<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Tests;

use CodeConjure\Gls\Country;
use CodeConjure\Gls\Environment;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CountryTest extends TestCase
{
    /** @return iterable<string, array{Country, Environment, string}> */
    public static function urlProvider(): iterable
    {
        yield 'HU éles' => [Country::Hungary, Environment::Production, 'https://api.mygls.hu'];
        yield 'HU teszt' => [Country::Hungary, Environment::Test, 'https://api.test.mygls.hu'];
        yield 'CZ éles' => [Country::Czechia, Environment::Production, 'https://api.mygls.cz'];
        yield 'HR teszt' => [Country::Croatia, Environment::Test, 'https://api.test.mygls.hr'];
        yield 'RO éles' => [Country::Romania, Environment::Production, 'https://api.mygls.ro'];
        yield 'SI éles' => [Country::Slovenia, Environment::Production, 'https://api.mygls.si'];
        yield 'SK éles' => [Country::Slovakia, Environment::Production, 'https://api.mygls.sk'];
        yield 'RS éles' => [Country::Serbia, Environment::Production, 'https://api.mygls.rs'];
    }

    #[DataProvider('urlProvider')]
    public function testEveryCountryHasItsOwnApiDomain(Country $country, Environment $environment, string $expected): void
    {
        self::assertSame($expected, $country->apiBaseUrl($environment));
    }

    public function testTheBackingValueIsTheIsoCode(): void
    {
        self::assertSame(Country::Hungary, Country::from('HU'));
    }

    public function testTheSandboxFlagSelectsTheTestEnvironment(): void
    {
        self::assertSame(Environment::Test, Environment::fromSandboxFlag(true));
        self::assertSame(Environment::Production, Environment::fromSandboxFlag(false));
    }
}
