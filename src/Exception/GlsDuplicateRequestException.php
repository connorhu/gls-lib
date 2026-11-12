<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Exception;

/**
 * A MyGLS 31-es hibája: „Same request sent 5 times within last 5 minutes".
 *
 * Külön osztály, mert az újrapróbálkozó logikának pont ezt kell felismernie:
 * a vak retry maga zárja ki a kérést. A dokumentáció kiemeli, hogy az üzenet
 * számai változók — a felismerés ezért a KÓDON alapul, nem a szövegen.
 */
final class GlsDuplicateRequestException extends GlsApiException
{
    public const int ERROR_CODE = 31;
}
