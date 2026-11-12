<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

/** A `GetParcelList` eredménye: a dátumtartományba eső csomagok címkeadatai. */
final readonly class ParcelListResult extends Result
{
    /**
     * @param list<PrintDataInfo> $printData
     * @param list<ErrorInfo> $errors
     */
    public function __construct(
        public array $printData,
        array $errors,
    ) {
        parent::__construct($errors);
    }
}
