<?php

declare(strict_types=1);

namespace CodeConjure\Gls;

/**
 * A MyGLS országonként külön domainen fut (a dokumentáció „Country domain API
 * URLs" táblája). A backing érték az ISO 3166-1 kód.
 */
enum Country: string
{
    case Croatia = 'HR';
    case Czechia = 'CZ';
    case Hungary = 'HU';
    case Romania = 'RO';
    case Slovenia = 'SI';
    case Slovakia = 'SK';
    case Serbia = 'RS';

    public function apiBaseUrl(Environment $environment): string
    {
        $tld = strtolower($this->value);

        return match ($environment) {
            Environment::Production => 'https://api.mygls.' . $tld,
            Environment::Test => 'https://api.test.mygls.' . $tld,
        };
    }
}
