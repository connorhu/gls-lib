<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

/** A `ModifyCOD` eredménye. */
final readonly class ModifyCodResult extends Result
{
    /**
     * @param list<ErrorInfo> $errors
     */
    public function __construct(
        public bool $successful,
        array $errors,
    ) {
        parent::__construct($errors);
    }
}
