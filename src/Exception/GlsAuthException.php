<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Exception;

/**
 * Hitelesítési hiba: HTTP 401/403, vagy a MyGLS `-1`-es hibakódja.
 *
 * A `-1` nincs benne a dokumentáció A függelékében, de mérve (2026-11-12) ez
 * jön HTTP 200 mellett rossz felhasználónévre/jelszóra („Unauthorized.") ÉS a
 * túl sok sikertelen belépés utáni zárolásra („Too many failed login
 * attempts. Your account has been locked until …") is. Egyiket sem szabad
 * újrapróbálni: a második a próbálkozástól csak tovább tart.
 */
class GlsAuthException extends GlsApiException
{
}
