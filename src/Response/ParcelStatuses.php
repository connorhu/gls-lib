<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

use CodeConjure\Gls\Internal\Payload;

/** Egy csomag státusztörténete a tömeges lekérdezésben (`ParcelStatuses`). */
final readonly class ParcelStatuses
{
    /** @param list<ParcelStatus> $statuses */
    public function __construct(
        public int $parcelNumber,
        public ?int $clientNumber,
        public ?string $clientReference,
        public ?float $weight,
        public array $statuses,
    ) {
    }

    /** @internal */
    public static function fromPayload(Payload $payload): self
    {
        return new self(
            $payload->int('ParcelNumber'),
            $payload->nullableInt('ClientNumber'),
            $payload->nullableString('ClientReference'),
            $payload->nullableFloat('Weight'),
            array_map(ParcelStatus::fromPayload(...), $payload->objects('ParcelStatusList')),
        );
    }
}
