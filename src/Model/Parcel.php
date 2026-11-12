<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Model;

use CodeConjure\Gls\Codec\DotNetDate;

/**
 * A MyGLS `Parcel` osztálya — egy feladás (akár több darabbal, `count`).
 *
 * A konstruktor a dokumentált, szerveroldalon is elutasított szabályokat
 * előre ellenőrzi (A függelék 8, 28, 29, 30; B függelék PSD), hogy a hiba ne
 * egy félig sikeres kötegből derüljön ki.
 */
final readonly class Parcel
{
    public ?string $codCurrency;

    /**
     * @param list<Service> $services
     * @param list<ParcelProperty> $properties
     */
    public function __construct(
        public int $clientNumber,
        public Address $pickupAddress,
        public Address $deliveryAddress,
        public ?string $clientReference = null,
        public int $count = 1,
        public int|float|null $codAmount = null,
        public ?string $codReference = null,
        ?string $codCurrency = null,
        public ?string $content = null,
        public ?\DateTimeInterface $pickupDate = null,
        public array $services = [],
        public array $properties = [],
        public ?Address $finalDeliveryAddress = null,
    ) {
        if ($clientNumber <= 0) {
            throw new \InvalidArgumentException('A MyGLS ügyfélszám pozitív egész.');
        }

        if ($count < 1 || $count > 99) {
            throw new \InvalidArgumentException(\sprintf('A darabszám 1 és 99 között lehet, nem %d (29-es hiba).', $count));
        }

        if ($codAmount !== null && $codAmount < 0) {
            throw new \InvalidArgumentException('Az utánvét összege nem lehet negatív (8-as hiba).');
        }

        if ($codCurrency !== null && preg_match('/^[A-Za-z]{3}$/', $codCurrency) !== 1) {
            throw new \InvalidArgumentException(\sprintf('A pénznem háromjegyű ISO 4217 kód, nem "%s" (48-as hiba).', $codCurrency));
        }

        $this->codCurrency = $codCurrency === null ? null : strtoupper($codCurrency);

        $codes = array_map(static fn (Service $service): string => $service->code, $services);

        if (\count($codes) !== \count(array_unique($codes))) {
            throw new \InvalidArgumentException('Egy szolgáltatáskód legfeljebb egyszer szerepelhet a csomagon.');
        }

        if (\in_array('INS', $codes, true) && $count !== 1) {
            throw new \InvalidArgumentException('Az INS szolgáltatással a darabszám csak 1 lehet (28-as hiba).');
        }

        if (\in_array('FSS', $codes, true) && !\in_array('FDS', $codes, true)) {
            throw new \InvalidArgumentException('Az FSS szolgáltatás FDS nélkül nem kérhető (30-as hiba).');
        }

        if (\in_array('PSD', $codes, true) && !$deliveryAddress->hasFullContact()) {
            throw new \InvalidArgumentException('A PSD szolgáltatáshoz a kézbesítési címen ContactName, ContactPhone és ContactEmail kötelező.');
        }
    }

    public function hasService(string $code): bool
    {
        foreach ($this->services as $service) {
            if ($service->code === strtoupper($code)) {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $array = [
            'ClientNumber' => $this->clientNumber,
            'Count' => $this->count,
            'PickupAddress' => $this->pickupAddress->toArray(),
            'DeliveryAddress' => $this->deliveryAddress->toArray(),
            'ServiceList' => array_map(static fn (Service $service): array => $service->toArray(), $this->services),
        ];

        $optional = [
            'ClientReference' => $this->clientReference,
            'CODAmount' => $this->codAmount,
            'CODReference' => $this->codReference,
            'CODCurrency' => $this->codCurrency,
            'Content' => $this->content,
            'PickupDate' => $this->pickupDate === null ? null : DotNetDate::encode($this->pickupDate),
            'ParcelPropertyList' => $this->properties === []
                ? null
                : array_map(static fn (ParcelProperty $property): array => $property->toArray(), $this->properties),
            'FinalDeliveryAddress' => $this->finalDeliveryAddress?->toArray(),
        ];

        foreach ($optional as $key => $value) {
            if ($value !== null) {
                $array[$key] = $value;
            }
        }

        return $array;
    }
}
