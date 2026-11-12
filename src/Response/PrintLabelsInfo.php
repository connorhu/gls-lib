<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

use CodeConjure\Gls\Internal\Payload;

/**
 * A `PrintLabels_20251022` tétele. A `pin` csak címke nélküli automatás
 * feladásnál (`PickupType = 2`) jön.
 */
final readonly class PrintLabelsInfo
{
    public function __construct(
        public int $parcelId,
        public ?string $clientReference,
        public ?int $parcelNumber,
        public ?string $pin,
    ) {
    }

    /** @internal */
    public static function fromPayload(Payload $payload): self
    {
        return new self(
            $payload->int('ParcelId'),
            $payload->nullableString('ClientReference'),
            $payload->nullableInt('ParcelNumber'),
            $payload->nullableString('PIN'),
        );
    }
}
