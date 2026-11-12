<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Model;

/**
 * A csomag méretei és csomagolása (`ParcelPropertyList` elem).
 *
 * A dokumentáció nem nevezi meg a mértékegységet; a mintája (32 × 28 × 49,
 * 3,2) cm-re és kg-ra utal. A Szerbián kívül opcionális, de ügyfélszámtól
 * függően a súly kötelező lehet.
 */
final readonly class ParcelProperty
{
    public function __construct(
        public PackageType $packageType = PackageType::Box,
        public ?int $height = null,
        public ?int $length = null,
        public ?int $width = null,
        public ?float $weight = null,
        public ?string $content = null,
    ) {
        foreach (['height' => $height, 'length' => $length, 'width' => $width] as $field => $value) {
            if ($value !== null && $value <= 0) {
                throw new \InvalidArgumentException(\sprintf('A csomag `%s` mérete pozitív egész.', $field));
            }
        }

        if ($weight !== null && $weight <= 0) {
            throw new \InvalidArgumentException('A csomag súlya pozitív.');
        }
    }

    /** @return array<string, int|float|string> */
    public function toArray(): array
    {
        return array_filter([
            'PackageType' => $this->packageType->value,
            'Height' => $this->height,
            'Length' => $this->length,
            'Width' => $this->width,
            'Weight' => $this->weight,
            'Content' => $this->content,
        ], static fn (int|float|string|null $value): bool => $value !== null);
    }
}
