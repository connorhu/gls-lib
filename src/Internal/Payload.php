<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Internal;

use CodeConjure\Gls\Codec\DotNetDate;
use CodeConjure\Gls\Exception\GlsApiException;

/**
 * Típusos olvasás egy dekódolt MyGLS JSON-objektumból.
 *
 * A hiányzó és a `null` mező egyformán „nincs"; a rossz típus protokoll-
 * hiba (GlsApiException), nem csendes átalakítás.
 *
 * @internal
 */
final readonly class Payload
{
    /** @param array<mixed> $data */
    public function __construct(private array $data)
    {
    }

    /** @return array<mixed> */
    public function raw(): array
    {
        return $this->data;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function get(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function int(string $key): int
    {
        return $this->nullableInt($key) ?? throw new GlsApiException(\sprintf('A MyGLS válaszból hiányzik a `%s` mező.', $key));
    }

    /**
     * Egész, vagy számjegyekből álló string: a hibakód mérve HOL szám
     * (`-1`), HOL string (`"1000"`).
     */
    public function nullableInt(string $key): ?int
    {
        $value = $this->get($key);

        return match (true) {
            $value === null => null,
            \is_int($value) => $value,
            \is_string($value) && preg_match('/^-?\d+$/', $value) === 1 => (int) $value,
            default => throw new GlsApiException(\sprintf('A MyGLS válasz `%s` mezője nem egész szám.', $key)),
        };
    }

    public function nullableFloat(string $key): ?float
    {
        $value = $this->get($key);

        return match (true) {
            $value === null => null,
            \is_int($value), \is_float($value) => (float) $value,
            default => throw new GlsApiException(\sprintf('A MyGLS válasz `%s` mezője nem szám.', $key)),
        };
    }

    public function nullableString(string $key): ?string
    {
        $value = $this->get($key);

        return match (true) {
            $value === null => null,
            \is_string($value) => $value,
            \is_int($value) => (string) $value,
            default => throw new GlsApiException(\sprintf('A MyGLS válasz `%s` mezője nem szöveg.', $key)),
        };
    }

    public function string(string $key): string
    {
        return $this->nullableString($key) ?? '';
    }

    public function bool(string $key): bool
    {
        $value = $this->get($key);

        return match (true) {
            $value === null => false,
            \is_bool($value) => $value,
            default => throw new GlsApiException(\sprintf('A MyGLS válasz `%s` mezője nem logikai érték.', $key)),
        };
    }

    public function date(string $key): \DateTimeImmutable
    {
        return DotNetDate::decodeNullable($this->get($key))
            ?? throw new GlsApiException(\sprintf('A MyGLS válaszból hiányzik a `%s` dátum.', $key));
    }

    public function nullableDate(string $key): ?\DateTimeImmutable
    {
        return DotNetDate::decodeNullable($this->get($key));
    }

    /** @return list<self> */
    public function objects(string $key): array
    {
        $objects = [];

        foreach ($this->list($key) as $item) {
            if (!\is_array($item)) {
                throw new GlsApiException(\sprintf('A MyGLS válasz `%s` listájában nem objektum áll.', $key));
            }

            $objects[] = new self($item);
        }

        return $objects;
    }

    /** @return list<int> */
    public function ints(string $key): array
    {
        $ints = [];

        foreach ($this->list($key) as $item) {
            if (!\is_int($item)) {
                throw new GlsApiException(\sprintf('A MyGLS válasz `%s` listájában nem egész szám áll.', $key));
            }

            $ints[] = $item;
        }

        return $ints;
    }

    /** @return list<string> */
    public function strings(string $key): array
    {
        $strings = [];

        foreach ($this->list($key) as $item) {
            if (!\is_string($item)) {
                throw new GlsApiException(\sprintf('A MyGLS válasz `%s` listájában nem szöveg áll.', $key));
            }

            $strings[] = $item;
        }

        return $strings;
    }

    /** @return list<mixed> */
    private function list(string $key): array
    {
        $value = $this->get($key);

        if ($value === null) {
            return [];
        }

        if (!\is_array($value) || !array_is_list($value)) {
            throw new GlsApiException(\sprintf('A MyGLS válasz `%s` mezője nem lista.', $key));
        }

        return $value;
    }
}
