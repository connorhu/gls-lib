<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

/** A `PrepareLabels` eredménye: az előkészített csomagok azonosítói. */
final readonly class PrepareLabelsResult extends Result
{
    /**
     * @param list<ParcelInfo> $parcels
     * @param list<ErrorInfo> $errors
     */
    public function __construct(
        public array $parcels,
        array $errors,
    ) {
        parent::__construct($errors);
    }
}
