<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Codec;

use CodeConjure\Gls\Exception\GlsApiException;

/**
 * A WCF JSON a `byte[]` mezőt egész számok tömbjeként írja és várja
 * (`[37,80,68,70,…]`), nem base64-ként.
 */
final class ByteArray
{
    /** @return list<int> */
    public static function encode(string $binary): array
    {
        if ($binary === '') {
            return [];
        }

        return array_map(\ord(...), str_split($binary));
    }

    public static function decode(mixed $wire): string
    {
        if (!\is_array($wire) || !array_is_list($wire)) {
            throw new GlsApiException('A MyGLS bájttömb nem egész számok listája.');
        }

        foreach ($wire as $byte) {
            if (!\is_int($byte) || $byte < 0 || $byte > 255) {
                throw new GlsApiException('A MyGLS bájttömb 0–255 tartományon kívüli elemet tartalmaz.');
            }
        }

        return $wire === [] ? '' : pack('C*', ...$wire);
    }

    /** Null vagy üres tömb → null (a MyGLS mindkettőt használja „nincs" jelentésben). */
    public static function decodeNullable(mixed $wire): ?string
    {
        if ($wire === null || $wire === []) {
            return null;
        }

        return self::decode($wire);
    }
}
