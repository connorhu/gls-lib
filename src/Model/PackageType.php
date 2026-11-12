<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Model;

/** A `ParcelProperty.PackageType` értékei. */
enum PackageType: int
{
    case Colli = 1;
    case Box = 2;
    case Roll = 3;
    case Can = 4;
    case Case = 5;
    case Reel = 6;
    case Sack = 7;
}
