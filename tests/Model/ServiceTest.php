<?php

declare(strict_types=1);

namespace CodeConjure\Gls\Tests\Model;

use CodeConjure\Gls\Model\Service;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Az elvárt alakok a dokumentáció E függelékének JSON-mintái.
 */
final class ServiceTest extends TestCase
{
    /** @return iterable<string, array{Service, array<string, mixed>}> */
    public static function appendixEProvider(): iterable
    {
        yield 'COD' => [Service::cod(), ['Code' => 'COD']];
        yield 'PSD' => [Service::psd('2351-CSOMAGPONT'), ['Code' => 'PSD', 'PSDParameter' => ['StringValue' => '2351-CSOMAGPONT']]];
        yield 'FDS' => [Service::fds('something@anything.hu'), ['Code' => 'FDS', 'FDSParameter' => ['Value' => 'something@anything.hu']]];
        yield 'FSS' => [Service::fss('+36701234567'), ['Code' => 'FSS', 'FSSParameter' => ['Value' => '+36701234567']]];
        yield 'SM2' => [Service::sm2('+36701234567'), ['Code' => 'SM2', 'SM2Parameter' => ['Value' => '+36701234567']]];
        yield 'CS1' => [Service::cs1('+36701234567'), ['Code' => 'CS1', 'CS1Parameter' => ['Value' => '+36701234567']]];
        yield 'AOS' => [Service::aos('Test Addressee'), ['Code' => 'AOS', 'AOSParameter' => ['Value' => 'Test Addressee']]];
        yield 'INS' => [Service::ins(50000), ['Code' => 'INS', 'INSParameter' => ['Value' => '50000']]];
        yield 'DDS' => [Service::dds(new \DateTimeImmutable('2020-08-31T21:59:59Z')), ['Code' => 'DDS', 'DDSParameter' => ['Value' => '/Date(1598911199000)/']]];
        yield 'SDS' => [
            Service::sds(new \DateTimeImmutable('1970-01-01T12:21:00Z'), new \DateTimeImmutable('1970-01-01T15:51:00Z')),
            ['Code' => 'SDS', 'SDSParameter' => ['TimeFrom' => '/Date(44460000)/', 'TimeTo' => '/Date(57060000)/']],
        ];
        yield 'paraméter nélküli' => [Service::simple('24H'), ['Code' => '24H']];
    }

    /** @param array<string, mixed> $expected */
    #[DataProvider('appendixEProvider')]
    public function testItSerializesLikeTheAppendixESamples(Service $service, array $expected): void
    {
        self::assertSame($expected, $service->toArray());
    }

    public function testTheCodeIsExposed(): void
    {
        self::assertSame('PSD', Service::psd('2351-CSOMAGPONT')->code);
    }

    public function testASimpleServiceCannotSmuggleInAParameterisedCode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Service::simple('PSD');
    }

    public function testAnEmptyParameterIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Service::psd(' ');
    }

    public function testANegativeInsuranceValueIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Service::ins(-1);
    }

    public function testTheScheduledWindowMustBeOrdered(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Service::sds(new \DateTimeImmutable('1970-01-01T15:00:00Z'), new \DateTimeImmutable('1970-01-01T12:00:00Z'));
    }
}
