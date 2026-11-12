<?php

declare(strict_types=1);

namespace CodeConjure\Gls;

/**
 * A MyGLS `APIRequestBase`-e: minden kérés hordozza.
 *
 * A jelszót a konstruktor azonnal SHA-512-vel hasheli, és csak a digestet
 * tartja meg — a dokumentáció szerint a szerver is csak ezt kapja. Dumpba és
 * szerializálásba a digest sem kerül (`__debugInfo`, `__serialize`).
 */
final class Credentials
{
    private readonly string $passwordDigest;

    public function __construct(
        public readonly string $username,
        #[\SensitiveParameter]
        string $password,
        public readonly int $clientNumber,
        public readonly string $webshopEngine = 'Custom',
    ) {
        if (trim($username) === '') {
            throw new \InvalidArgumentException('A MyGLS felhasználónév nem lehet üres.');
        }

        if ($clientNumber <= 0) {
            throw new \InvalidArgumentException('A MyGLS ügyfélszám pozitív egész.');
        }

        $this->passwordDigest = hash('sha512', $password, true);
    }

    /**
     * @return array{Username: string, Password: list<int>, WebshopEngine: string}
     */
    public function toRequestBase(): array
    {
        return [
            'Username' => $this->username,
            'Password' => Codec\ByteArray::encode($this->passwordDigest),
            'WebshopEngine' => $this->webshopEngine,
        ];
    }

    /** @return array<string, mixed> */
    public function __debugInfo(): array
    {
        return [
            'username' => $this->username,
            'clientNumber' => $this->clientNumber,
            'webshopEngine' => $this->webshopEngine,
            'password' => '***',
        ];
    }

    /** @return array<string, mixed> */
    public function __serialize(): array
    {
        throw new \LogicException('A MyGLS hitelesítő adatai nem szerializálhatók.');
    }
}
