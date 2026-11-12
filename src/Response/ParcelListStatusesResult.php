<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

/** A `GetParcelListStatuses` eredménye: csomagonként a státusztörténet. */
final readonly class ParcelListStatusesResult extends Result
{
    /**
     * @param list<ParcelStatuses> $parcels
     * @param list<ErrorInfo> $errors
     */
    public function __construct(
        public array $parcels,
        array $errors,
    ) {
        parent::__construct($errors);
    }

    public function forParcelNumber(int $parcelNumber): ?ParcelStatuses
    {
        foreach ($this->parcels as $parcel) {
            if ($parcel->parcelNumber === $parcelNumber) {
                return $parcel;
            }
        }

        return null;
    }
}
