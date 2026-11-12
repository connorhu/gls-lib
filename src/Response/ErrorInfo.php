<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

use CodeConjure\Gls\Internal\Payload;

/**
 * Egy MyGLS hiba (`ErrorInfo`). A `code` az A függelék száma — a szerver
 * hol számként, hol stringként küldi, itt mindig `int`.
 */
final readonly class ErrorInfo
{
    /**
     * @param list<string> $clientReferences
     * @param list<int> $parcelIds
     */
    public function __construct(
        public int $code,
        public string $description,
        public array $clientReferences = [],
        public array $parcelIds = [],
    ) {
    }

    /** @internal */
    public static function fromPayload(Payload $payload): self
    {
        return new self(
            $payload->nullableInt('ErrorCode') ?? 0,
            $payload->string('ErrorDescription'),
            $payload->strings('ClientReferenceList'),
            $payload->ints('ParcelIdList'),
        );
    }
}
