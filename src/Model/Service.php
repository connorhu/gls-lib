<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Model;

use CodeConjure\Gls\Codec\DotNetDate;

/**
 * Egy MyGLS szolgáltatás a csomagon (B függelék), a paraméterével együtt.
 *
 * A paraméteres szolgáltatások csak a saját factory-jukon át jönnek létre,
 * így a `<Code>Parameter` kulcs és alakja nem csúszhat el; a paraméter
 * nélküliekre a `simple()` való.
 */
final readonly class Service
{
    /**
     * Paraméterrel élő kódok — ezeket a `simple()` nem fogadja.
     */
    private const array PARAMETERISED = ['ADR', 'AOS', 'CS1', 'DDS', 'DPV', 'FDS', 'FSS', 'INS', 'MMP', 'PSD', 'SDS', 'SM1', 'SM2', 'SZL'];

    /**
     * @param array<string, string>|null $parameter
     */
    private function __construct(
        public string $code,
        private ?array $parameter = null,
    ) {
    }

    /**
     * Paraméter nélküli szolgáltatás: `24H`, `COD`, `PRS`, `PSS`, `SAT`,
     * `SRS`, `T09`, `T10`, `T12`, `TGS`, `XS`, `LRS`, …
     */
    public static function simple(string $code): self
    {
        $code = strtoupper(trim($code));

        if (preg_match('/^[A-Z0-9]{2,3}$/', $code) !== 1) {
            throw new \InvalidArgumentException(\sprintf('Érvénytelen MyGLS szolgáltatáskód: "%s".', $code));
        }

        if (\in_array($code, self::PARAMETERISED, true)) {
            throw new \InvalidArgumentException(\sprintf('A `%s` szolgáltatásnak paramétere van — a saját factory-ját használd.', $code));
        }

        return new self($code);
    }

    /** Utánvét. Az összeg a `Parcel::$codAmount`-on megy. */
    public static function cod(): self
    {
        return new self('COD');
    }

    /** ParcelShop / csomagautomata kézbesítés; a pont azonosítója (pl. `2351-CSOMAGPONT`). */
    public static function psd(string $deliveryPointId): self
    {
        return new self('PSD', ['StringValue' => self::nonEmpty('PSD', $deliveryPointId)]);
    }

    /** Flexibilis kézbesítés — e-mail értesítés. */
    public static function fds(string $email): self
    {
        return self::valued('FDS', $email);
    }

    /** Flexibilis kézbesítés — SMS értesítés (nemzetközi formátum; FDS nélkül nem kérhető). */
    public static function fss(string $phone): self
    {
        return self::valued('FSS', $phone);
    }

    /** SMS előrejelzés (nemzetközi formátum). */
    public static function sm2(string $phone): self
    {
        return self::valued('SM2', $phone);
    }

    /** Kapcsolattartó telefonszám. */
    public static function cs1(string $phone): self
    {
        return self::valued('CS1', $phone);
    }

    /** Csak a címzettnek adható át. */
    public static function aos(string $addressee): self
    {
        return self::valued('AOS', $addressee);
    }

    /** Biztosítás a csomag értékére (a doksi: HU-ban 50 000 Ft alatt alapbiztosítás van). */
    public static function ins(int|float $value): self
    {
        if ($value < 0) {
            throw new \InvalidArgumentException('Az INS értéke nem lehet negatív.');
        }

        return new self('INS', ['Value' => self::decimal($value)]);
    }

    /** Adott napi kézbesítés. */
    public static function dds(\DateTimeInterface $day): self
    {
        return new self('DDS', ['Value' => DotNetDate::encode($day)]);
    }

    /**
     * Időablakos kézbesítés. A doksi szerint csak az időrész számít, és UTC;
     * a szerver 10 percre kerekít felfelé.
     */
    public static function sds(\DateTimeInterface $from, \DateTimeInterface $to): self
    {
        if ($from >= $to) {
            throw new \InvalidArgumentException('Az SDS időablak kezdete a vége előtt kell legyen.');
        }

        return new self('SDS', ['TimeFrom' => DotNetDate::encode($from), 'TimeTo' => DotNetDate::encode($to)]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $array = ['Code' => $this->code];

        if ($this->parameter !== null) {
            $array[$this->code . 'Parameter'] = $this->parameter;
        }

        return $array;
    }

    private static function valued(string $code, string $value): self
    {
        return new self($code, ['Value' => self::nonEmpty($code, $value)]);
    }

    private static function nonEmpty(string $code, string $value): string
    {
        if (trim($value) === '') {
            throw new \InvalidArgumentException(\sprintf('A `%s` szolgáltatás paramétere nem lehet üres.', $code));
        }

        return $value;
    }

    private static function decimal(int|float $value): string
    {
        if (\is_int($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
    }
}
