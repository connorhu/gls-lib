<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Exception;

/**
 * Minden kivétel őse, amit a kliens dob: kapcsolódási hiba, nem 2xx HTTP,
 * értelmezhetetlen válasz, vagy a kérés EGÉSZÉT elutasító MyGLS-hiba.
 *
 * A csomagonkénti (részleges) hibák NEM kivételek: azokat az eredmény-
 * objektumok `errors` listája hordozza, a sikeres tételek mellett.
 */
class GlsApiException extends \RuntimeException
{
}
