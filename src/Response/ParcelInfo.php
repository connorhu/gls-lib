<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

use CodeConjure\Gls\Internal\Payload;

/** Egy előkészített csomag (`ParcelInfo`): a MyGLS adatbázis-azonosítója. */
final readonly class ParcelInfo
{
    public function __construct(
        public int $parcelId,
        public ?string $clientReference,
    ) {
    }

    /** @internal */
    public static function fromPayload(Payload $payload): self
    {
        return new self($payload->int('ParcelId'), $payload->nullableString('ClientReference'));
    }
}
