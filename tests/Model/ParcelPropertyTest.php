<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Tests\Model;

use CodeConjure\Gls\Model\PackageType;
use CodeConjure\Gls\Model\ParcelProperty;
use PHPUnit\Framework\TestCase;

final class ParcelPropertyTest extends TestCase
{
    public function testOnlyTheGivenDimensionsAreSent(): void
    {
        self::assertSame(
            ['PackageType' => 1, 'Weight' => 0.5],
            (new ParcelProperty(packageType: PackageType::Colli, weight: 0.5))->toArray(),
        );
    }

    public function testTheWeightKeepsItsFractionInJson(): void
    {
        self::assertSame(
            '{"PackageType":2,"Weight":3.0}',
            json_encode((new ParcelProperty(packageType: PackageType::Box, weight: 3.0))->toArray(), \JSON_PRESERVE_ZERO_FRACTION),
        );
    }

    public function testDimensionsArePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ParcelProperty(packageType: PackageType::Box, height: 0);
    }

    public function testTheWeightIsPositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new ParcelProperty(packageType: PackageType::Box, weight: 0.0);
    }

    public function testThePackageTypesMatchTheDocumentation(): void
    {
        self::assertSame(
            ['Colli' => 1, 'Box' => 2, 'Roll' => 3, 'Can' => 4, 'Case' => 5, 'Reel' => 6, 'Sack' => 7],
            array_combine(
                array_map(static fn (PackageType $t): string => $t->name, PackageType::cases()),
                array_map(static fn (PackageType $t): int => $t->value, PackageType::cases()),
            ),
        );
    }
}
