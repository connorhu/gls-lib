<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

/** A `DeleteLabels` eredménye: a sztornózott csomagok. */
final readonly class DeleteLabelsResult extends Result
{
    /**
     * @param list<DeletedParcel> $deleted
     * @param list<ErrorInfo> $errors
     */
    public function __construct(
        public array $deleted,
        array $errors,
    ) {
        parent::__construct($errors);
    }
}
