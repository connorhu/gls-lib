<?php

declare(strict_types=1);

namespace CodeConjure\Gls;

/**
 * A címke formátuma (`TypeOfPrinter`).
 *
 * Az A4-es formátumoknál a `PrintPosition` (1–4) mondja meg, melyik
 * negyedtől kezdődjön a nyomtatás — egy részben elhasznált címkés lap így
 * újra betehető.
 */
enum LabelFormat: string
{
    case A4_2x2 = 'A4_2x2';
    case A4_4x1 = 'A4_4x1';
    case Connect = 'Connect';
    case Thermo = 'Thermo';
    case ThermoZpl = 'ThermoZPL';
    case ThermoZpl300Dpi = 'ThermoZPL_300DPI';
    case ShipItThermoPdf = 'ShipItThermoPdf';
    case ShipItThermoZpl = 'ShipItThermoZpl';

    public function isA4(): bool
    {
        return $this === self::A4_2x2 || $this === self::A4_4x1;
    }
}
