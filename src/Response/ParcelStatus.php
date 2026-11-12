<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

use CodeConjure\Gls\Internal\Payload;

/**
 * Egy nyomkövetési esemény (`ParcelStatus`). A `code` a G függelék
 * státuszkódja, stringként (a szerver vezető nullával is küldheti).
 */
final readonly class ParcelStatus
{
    public function __construct(
        public string $code,
        public \DateTimeImmutable $date,
        public string $description,
        public ?string $info,
        public ?string $depotCity,
        public ?string $depotNumber,
    ) {
    }

    /** @internal */
    public static function fromPayload(Payload $payload): self
    {
        return new self(
            $payload->string('StatusCode'),
            $payload->date('StatusDate'),
            $payload->string('StatusDescription'),
            $payload->nullableString('StatusInfo'),
            $payload->nullableString('DepotCity'),
            $payload->nullableString('DepotNumber'),
        );
    }
}
