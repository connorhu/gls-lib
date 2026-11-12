<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

use CodeConjure\Gls\Internal\Payload;

/**
 * Egy sztornózott csomag (`SuccessfullyDeleted`). Ha a csomag több darabos
 * feladás része volt, a többi darab azonosítója a `subParcelIds`.
 */
final readonly class DeletedParcel
{
    /** @param list<int> $subParcelIds */
    public function __construct(
        public int $parcelId,
        public array $subParcelIds,
    ) {
    }

    /** @internal */
    public static function fromPayload(Payload $payload): self
    {
        return new self($payload->int('ParcelId'), $payload->ints('SubParcelIdList'));
    }
}
