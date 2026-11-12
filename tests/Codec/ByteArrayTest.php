<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Tests\Codec;

use CodeConjure\Gls\Codec\ByteArray;
use CodeConjure\Gls\Exception\GlsApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A WCF a `byte[]` mezőket JSON-ban egész számok tömbjeként írja (nem
 * base64-ként): így megy a jelszó-hash a kérésben, és így jön a címke-PDF
 * és a POD a válaszban.
 */
final class ByteArrayTest extends TestCase
{
    public function testItEncodesEveryByteAsAnUnsignedInteger(): void
    {
        self::assertSame([0, 37, 80, 255], ByteArray::encode("\x00%P\xFF"));
    }

    public function testAnEmptyStringIsAnEmptyList(): void
    {
        self::assertSame([], ByteArray::encode(''));
    }

    public function testItDecodesAPdfHeader(): void
    {
        self::assertSame('%PDF-1.4', ByteArray::decode([37, 80, 68, 70, 45, 49, 46, 52]));
    }

    public function testRoundTripOverEveryByteValue(): void
    {
        $binary = implode('', array_map('chr', range(0, 255)));

        self::assertSame($binary, ByteArray::decode(ByteArray::encode($binary)));
    }

    public function testNullDecodesToNull(): void
    {
        self::assertNull(ByteArray::decodeNullable(null));
    }

    public function testAnEmptyListDecodesToNullWhenNullable(): void
    {
        self::assertNull(ByteArray::decodeNullable([]));
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidProvider(): iterable
    {
        yield 'base64 string' => ['JVBERi0='];
        yield '255 fölött' => [[37, 256]];
        yield 'negatív' => [[-1]];
        yield 'nem egész' => [[37, '80']];
        yield 'asszociatív' => [['a' => 37]];
    }

    #[DataProvider('invalidProvider')]
    public function testAnythingButAListOfBytesIsAProtocolError(mixed $wire): void
    {
        $this->expectException(GlsApiException::class);

        ByteArray::decode($wire);
    }
}
