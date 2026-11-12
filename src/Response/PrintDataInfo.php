<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

use CodeConjure\Gls\Internal\Payload;

/**
 * A címkéhez tartozó adatok (`PrintDataInfo`, a `_20251022` verzió `PIN`-
 * jével). A visszaküldött `Parcel` a `raw` tömbben marad: a kliens nem
 * próbálja visszaépíteni a saját kérés-modelljére.
 */
final readonly class PrintDataInfo
{
    /** @param array<mixed> $raw */
    public function __construct(
        public int $parcelId,
        public ?string $clientReference,
        public ?int $parcelNumber,
        public ?int $parcelNumberWithCheckdigit,
        public ?string $depot,
        public ?string $depotNumber,
        public ?string $driver,
        public ?string $sort,
        public ?string $b2cChar,
        public ?string $pin,
        public array $raw = [],
    ) {
    }

    /** @internal */
    public static function fromPayload(Payload $payload): self
    {
        return new self(
            $payload->int('ParcelId'),
            $payload->nullableString('ClientReference'),
            $payload->nullableInt('ParcelNumber'),
            $payload->nullableInt('ParcelNumberWithCheckdigit'),
            $payload->nullableString('Depot'),
            $payload->nullableString('DepotNumber'),
            $payload->nullableString('Driver'),
            $payload->nullableString('Sort'),
            $payload->nullableString('B2CChar'),
            $payload->nullableString('PIN'),
            $payload->raw(),
        );
    }
}
