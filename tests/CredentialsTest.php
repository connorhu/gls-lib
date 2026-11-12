<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Tests;

use CodeConjure\Gls\Credentials;
use PHPUnit\Framework\TestCase;

final class CredentialsTest extends TestCase
{
    /**
     * Mérve a teszt-API-n (2026-11-12): a SHA-512 BÁJTOK egész-tömbként
     * mennek át a deszerializálón; a hex-stringet a szerver `1000`-es
     * hibával utasítja el.
     */
    public function testThePasswordTravelsAsTheSha512DigestBytes(): void
    {
        $base = (new Credentials('user@example.com', 'titok', 100000001))->toRequestBase();

        self::assertSame(array_values(unpack('C*', hash('sha512', 'titok', true)) ?: []), $base['Password']);
        self::assertCount(64, $base['Password']);
    }

    public function testTheRequestBaseCarriesTheUsernameAndTheWebshopEngine(): void
    {
        $base = (new Credentials('user@example.com', 'titok', 100000001, 'Sylius'))->toRequestBase();

        self::assertSame('user@example.com', $base['Username']);
        self::assertSame('Sylius', $base['WebshopEngine']);
    }

    /**
     * A doksi változásnaplója (2019-10-21): „ClientNumberList: do not use".
     * Az ügyfélszám a csomagon (`Parcel.ClientNumber`) megy.
     */
    public function testTheDeprecatedClientNumberListIsNotSent(): void
    {
        $base = (new Credentials('user@example.com', 'titok', 100000001))->toRequestBase();

        self::assertArrayNotHasKey('ClientNumberList', $base);
    }

    public function testItExposesTheClientNumber(): void
    {
        self::assertSame(100000001, (new Credentials('user@example.com', 'titok', 100000001))->clientNumber);
    }

    public function testThePlainPasswordIsNotKept(): void
    {
        $credentials = new Credentials('user@example.com', 'nagyon-titkos-jelszo', 100000001);

        self::assertStringNotContainsString('nagyon-titkos-jelszo', print_r($credentials, true));
        self::assertStringNotContainsString('nagyon-titkos-jelszo', var_export((array) $credentials, true));
    }

    /**
     * Egy session-be vagy cache-be szerializált hitelesítő adat a digesttel
     * együtt kerülne ki — ezt a kliens nem engedi.
     */
    public function testItRefusesToBeSerialized(): void
    {
        $this->expectException(\LogicException::class);

        serialize(new Credentials('user@example.com', 'titok', 100000001));
    }

    public function testADumpDoesNotLeakTheDigestEither(): void
    {
        $credentials = new Credentials('user@example.com', 'titok', 100000001);
        $digestHex = hash('sha512', 'titok');

        self::assertStringNotContainsString(substr($digestHex, 0, 16), bin2hex(print_r($credentials, true)));
        self::assertStringNotContainsString(hash('sha512', 'titok', true), print_r($credentials, true));
    }

    public function testAnEmptyUsernameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Credentials(' ', 'titok', 100000001);
    }

    public function testANonPositiveClientNumberIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Credentials('user@example.com', 'titok', 0);
    }
}
