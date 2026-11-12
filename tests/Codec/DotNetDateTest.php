<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Tests\Codec;

use CodeConjure\Gls\Codec\DotNetDate;
use CodeConjure\Gls\Exception\GlsApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A MyGLS a WCF JSON dátumformátumát beszéli: `/Date(<ms epoch>[±hhmm])/`.
 * A szám mindig UTC ezredmásodperc; az opcionális eltolás csak megjelenítési
 * tipp, az időpontot NEM módosítja.
 */
final class DotNetDateTest extends TestCase
{
    public function testItEncodesAsMillisecondsSinceTheEpoch(): void
    {
        self::assertSame(
            '/Date(1598911199000)/',
            DotNetDate::encode(new \DateTimeImmutable('2020-08-31T21:59:59Z')),
        );
    }

    public function testEncodingIgnoresTheTimezoneOfTheInput(): void
    {
        self::assertSame(
            '/Date(1598911199000)/',
            DotNetDate::encode(new \DateTimeImmutable('2020-08-31T23:59:59+02:00')),
        );
    }

    public function testEncodingKeepsMilliseconds(): void
    {
        self::assertSame(
            '/Date(1598911199123)/',
            DotNetDate::encode(new \DateTimeImmutable('2020-08-31T21:59:59.123456Z')),
        );
    }

    /** @return iterable<string, array{string, string}> */
    public static function decodableProvider(): iterable
    {
        yield 'eltolás nélkül' => ['/Date(1598911199000)/', '2020-08-31T21:59:59.000+00:00'];
        yield 'pozitív eltolással' => ['/Date(1598911199000+0200)/', '2020-08-31T21:59:59.000+00:00'];
        yield 'negatív eltolással' => ['/Date(1598911199000-0500)/', '2020-08-31T21:59:59.000+00:00'];
        yield 'ezredmásodperccel' => ['/Date(1598911199123)/', '2020-08-31T21:59:59.123+00:00'];
        yield 'epoch előtt' => ['/Date(-1000)/', '1969-12-31T23:59:59.000+00:00'];
        yield 'csak időrész (SDS)' => ['/Date(44460000)/', '1970-01-01T12:21:00.000+00:00'];
    }

    #[DataProvider('decodableProvider')]
    public function testItDecodesToAnUtcInstant(string $wire, string $expected): void
    {
        $decoded = DotNetDate::decode($wire);

        self::assertSame($expected, $decoded->format('Y-m-d\TH:i:s.vP'));
    }

    public function testRoundTrip(): void
    {
        $instant = new \DateTimeImmutable('2026-11-12T08:15:30.250Z');

        self::assertEquals($instant, DotNetDate::decode(DotNetDate::encode($instant)));
    }

    /** @return iterable<string, array{string}> */
    public static function garbageProvider(): iterable
    {
        yield 'ISO-dátum' => ['2020-08-31T21:59:59Z'];
        yield 'üres' => [''];
        yield 'szám nélkül' => ['/Date()/'];
        yield 'csonka' => ['/Date(1598911199000'];
    }

    #[DataProvider('garbageProvider')]
    public function testAnythingElseIsAProtocolError(string $wire): void
    {
        $this->expectException(GlsApiException::class);

        DotNetDate::decode($wire);
    }
}
