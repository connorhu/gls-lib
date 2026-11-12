<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

/** A `GetPrintedLabels` eredménye: a címke-PDF (vagy null, ha egy sem készült) és a címkeadatok. */
final readonly class PrintedLabelsResult extends Result
{
    /**
     * @param list<PrintDataInfo> $printData
     * @param list<ErrorInfo> $errors
     */
    public function __construct(
        public ?string $pdf,
        public array $printData,
        array $errors,
    ) {
        parent::__construct($errors);
    }
}
