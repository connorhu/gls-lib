<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Tests\Model;

use CodeConjure\Gls\Model\Address;
use CodeConjure\Gls\Model\PackageType;
use CodeConjure\Gls\Model\Parcel;
use CodeConjure\Gls\Model\ParcelProperty;
use CodeConjure\Gls\Model\Service;
use PHPUnit\Framework\TestCase;

final class ParcelTest extends TestCase
{
    public function testAMinimalParcel(): void
    {
        $parcel = new Parcel(clientNumber: 100000001, pickupAddress: self::sender(), deliveryAddress: self::recipient());

        self::assertSame([
            'ClientNumber' => 100000001,
            'Count' => 1,
            'PickupAddress' => self::sender()->toArray(),
            'DeliveryAddress' => self::recipient()->toArray(),
            'ServiceList' => [],
        ], $parcel->toArray());
    }

    public function testAFullParcel(): void
    {
        $parcel = new Parcel(
            clientNumber: 100000001,
            pickupAddress: self::sender(),
            deliveryAddress: self::recipient(withContact: true),
            clientReference: '000123',
            count: 2,
            codAmount: 12990,
            codReference: '000123',
            codCurrency: 'huf',
            content: 'Kotta',
            pickupDate: new \DateTimeImmutable('2020-08-31T21:59:59Z'),
            services: [Service::cod(), Service::psd('2351-CSOMAGPONT')],
            properties: [new ParcelProperty(packageType: PackageType::Box, height: 32, length: 28, width: 49, weight: 3.2, content: 'Test')],
            finalDeliveryAddress: self::recipient(),
        );

        self::assertSame([
            'ClientNumber' => 100000001,
            'Count' => 2,
            'PickupAddress' => self::sender()->toArray(),
            'DeliveryAddress' => self::recipient(withContact: true)->toArray(),
            'ServiceList' => [['Code' => 'COD'], ['Code' => 'PSD', 'PSDParameter' => ['StringValue' => '2351-CSOMAGPONT']]],
            'ClientReference' => '000123',
            'CODAmount' => 12990,
            'CODReference' => '000123',
            'CODCurrency' => 'HUF',
            'Content' => 'Kotta',
            'PickupDate' => '/Date(1598911199000)/',
            'ParcelPropertyList' => [['PackageType' => 2, 'Height' => 32, 'Length' => 28, 'Width' => 49, 'Weight' => 3.2, 'Content' => 'Test']],
            'FinalDeliveryAddress' => self::recipient()->toArray(),
        ], $parcel->toArray());
    }

    /** A 29-es hiba: „Parcel(s) count must be between 1 and 99". */
    public function testTheCountIsBetweenOneAndNinetyNine(): void
    {
        foreach ([0, 100] as $count) {
            try {
                new Parcel(clientNumber: 1, pickupAddress: self::sender(), deliveryAddress: self::recipient(), count: $count);
                self::fail(\sprintf('A %d darabszámot el kellett volna utasítani.', $count));
            } catch (\InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /** A 8-as hiba: „COD amount has to be >= 0". */
    public function testTheCodAmountIsNotNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Parcel(clientNumber: 1, pickupAddress: self::sender(), deliveryAddress: self::recipient(), codAmount: -1);
    }

    /** A 28-as hiba: „The Count must be 1 because of the INS service". */
    public function testInsuranceForcesASingleParcel(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Parcel(clientNumber: 1, pickupAddress: self::sender(), deliveryAddress: self::recipient(), count: 2, services: [Service::ins(50000)]);
    }

    /** A 30-as hiba: „FSS service is not available without FDS". */
    public function testFssNeedsFds(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('FDS');

        new Parcel(clientNumber: 1, pickupAddress: self::sender(), deliveryAddress: self::recipient(), services: [Service::fss('+36701234567')]);
    }

    public function testFssWithFdsIsAccepted(): void
    {
        $parcel = new Parcel(
            clientNumber: 1,
            pickupAddress: self::sender(),
            deliveryAddress: self::recipient(),
            services: [Service::fds('a@example.com'), Service::fss('+36701234567')],
        );

        self::assertCount(2, $parcel->services);
    }

    /**
     * B függelék: a PSD-hez a kézbesítési címen a ContactName, ContactPhone
     * és ContactEmail kötelező.
     */
    public function testParcelShopDeliveryNeedsTheFullRecipientContact(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PSD');

        new Parcel(clientNumber: 1, pickupAddress: self::sender(), deliveryAddress: self::recipient(), services: [Service::psd('2351-CSOMAGPONT')]);
    }

    public function testAServiceCodeAppearsAtMostOnce(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Parcel(clientNumber: 1, pickupAddress: self::sender(), deliveryAddress: self::recipient(), services: [Service::cod(), Service::cod()]);
    }

    public function testTheCurrencyIsAThreeLetterIsoCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Parcel(clientNumber: 1, pickupAddress: self::sender(), deliveryAddress: self::recipient(), codAmount: 100, codCurrency: 'Ft');
    }

    public function testHasServiceLooksUpByCode(): void
    {
        $parcel = new Parcel(clientNumber: 1, pickupAddress: self::sender(), deliveryAddress: self::recipient(), services: [Service::cod()]);

        self::assertTrue($parcel->hasService('COD'));
        self::assertFalse($parcel->hasService('PSD'));
    }

    private static function sender(): Address
    {
        return new Address(name: 'Bolt Kft.', street: 'Fő utca', city: 'Budapest', zipCode: '1011', countryIsoCode: 'HU', houseNumber: '1');
    }

    private static function recipient(bool $withContact = false): Address
    {
        return new Address(
            name: 'Kiss Anna',
            street: 'Kossuth tér',
            city: 'Pécs',
            zipCode: '7621',
            countryIsoCode: 'HU',
            houseNumber: '5',
            contactName: $withContact ? 'Kiss Anna' : null,
            contactPhone: $withContact ? '+36701234567' : null,
            contactEmail: $withContact ? 'anna@example.com' : null,
        );
    }
}
