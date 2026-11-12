<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Response;

/**
 * A MyGLS műveletek közös eredmény-őse: a sikeres tételek MELLETT a
 * csomagonkénti hibák listája. A részleges siker normális állapot — a hívó
 * dönti el, mit kezd vele.
 */
abstract readonly class Result
{
    /** @param list<ErrorInfo> $errors */
    public function __construct(public array $errors)
    {
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /** @return list<ErrorInfo> */
    public function errorsFor(string $clientReference): array
    {
        return array_values(array_filter(
            $this->errors,
            static fn (ErrorInfo $error): bool => \in_array($clientReference, $error->clientReferences, true),
        ));
    }
}
