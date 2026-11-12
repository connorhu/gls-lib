<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

/** A `PrintLabels` (előkészítés + címke egy lépésben) eredménye. */
final readonly class PrintLabelsResult extends Result
{
    /**
     * @param list<PrintLabelsInfo> $labels
     * @param list<ErrorInfo> $errors
     */
    public function __construct(
        public ?string $pdf,
        public array $labels,
        array $errors,
    ) {
        parent::__construct($errors);
    }
}
