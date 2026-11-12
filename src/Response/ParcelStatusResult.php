<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

/** A `GetParcelStatuses` eredménye: egy csomag története, kérésre a POD PDF-fel. */
final readonly class ParcelStatusResult extends Result
{
    /**
     * @param list<ParcelStatus> $statuses
     * @param list<ErrorInfo> $errors
     */
    public function __construct(
        public ?int $parcelNumber,
        public ?string $clientReference,
        public ?string $deliveryCountryCode,
        public ?string $deliveryZipCode,
        public ?float $weight,
        public array $statuses,
        public ?string $pod,
        array $errors,
    ) {
        parent::__construct($errors);
    }
}
