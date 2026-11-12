<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Codec;

use CodeConjure\Gls\Exception\GlsApiException;

/**
 * A WCF JSON dátumformátuma: `/Date(<ms epoch>[±hhmm])/`.
 *
 * A szám UTC ezredmásodperc; az eltolás csak tipp a megjelenítéshez, az
 * időpontot nem módosítja — ezért dekódoláskor eldobjuk.
 *
 * FIGYELEM: a dátum a JSON-ban `"\/Date(…)\/"` alakban kell, hogy menjen
 * (escape-elt perjellel) — a WCF csak így ismeri fel dátumként. A PHP
 * `json_encode()` alapból így ír; a `JSON_UNESCAPED_SLASHES` elrontaná.
 */
final class DotNetDate
{
    private const string PATTERN = '~^/Date\((-?\d+)([+-]\d{4})?\)/$~';

    public static function encode(\DateTimeInterface $dateTime): string
    {
        $milliseconds = (int) $dateTime->format('U') * 1000 + (int) $dateTime->format('v');

        return \sprintf('/Date(%d)/', $milliseconds);
    }

    public static function decode(string $wire): \DateTimeImmutable
    {
        if (preg_match(self::PATTERN, $wire, $matches) !== 1) {
            throw new GlsApiException(\sprintf('A MyGLS dátum nem értelmezhető: "%s".', $wire));
        }

        $milliseconds = (int) $matches[1];
        $seconds = intdiv($milliseconds, 1000);
        $remainder = $milliseconds % 1000;

        if ($remainder < 0) {
            --$seconds;
            $remainder += 1000;
        }

        $decoded = \DateTimeImmutable::createFromFormat(
            'U.u',
            \sprintf('%d.%06d', $seconds, $remainder * 1000),
            new \DateTimeZone('UTC'),
        );

        if ($decoded === false) {
            throw new GlsApiException(\sprintf('A MyGLS dátum nem értelmezhető: "%s".', $wire));
        }

        return $decoded;
    }

    public static function decodeNullable(mixed $wire): ?\DateTimeImmutable
    {
        if ($wire === null) {
            return null;
        }

        if (!\is_string($wire)) {
            throw new GlsApiException('A MyGLS dátum nem string.');
        }

        return self::decode($wire);
    }
}
