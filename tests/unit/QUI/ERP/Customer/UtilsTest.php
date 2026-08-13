<?php

declare(strict_types=1);

namespace QUI\ERP\Customer;

use PHPUnit\Framework\TestCase;
use QUI;
use ReflectionMethod;

final class UtilsTest extends TestCase
{
    public function testEmailResolutionPrefersErpAddress(): void
    {
        $Address = $this->createMock(QUI\Users\Address::class);
        $Address->method('getMailList')->willReturn(['erp@example.invalid']);
        $Customer = $this->createMock(QUI\Interfaces\Users\User::class);
        $Customer->method('getAttribute')->willReturnMap([
            ['quiqqer.erp.address', 'erp-address'],
            ['email', 'user@example.invalid']
        ]);
        $Customer->method('getAddress')->with('erp-address')->willReturn($Address);

        self::assertSame('erp@example.invalid', Utils::getInstance()->getEmailByCustomer($Customer));
    }

    public function testEmailResolutionFallsBackToStandardAddressAndUser(): void
    {
        $StandardAddress = $this->createMock(QUI\Users\Address::class);
        $StandardAddress->method('getMailList')->willReturn(['standard@example.invalid']);
        $Customer = $this->createMock(QUI\Interfaces\Users\User::class);
        $Customer->method('getAttribute')->willReturnMap([
            ['quiqqer.erp.address', 'missing-address'],
            ['email', 'user@example.invalid']
        ]);
        $Customer->method('getAddress')->willThrowException(new QUI\Exception('missing'));
        $Customer->method('getStandardAddress')->willReturn($StandardAddress);

        self::assertSame('standard@example.invalid', Utils::getInstance()->getEmailByCustomer($Customer));

        $EmptyAddress = $this->createMock(QUI\Users\Address::class);
        $EmptyAddress->method('getMailList')->willReturn([]);
        $CustomerWithoutAddressMail = $this->createMock(QUI\Interfaces\Users\User::class);
        $CustomerWithoutAddressMail->method('getAttribute')->willReturnMap([
            ['quiqqer.erp.address', 'missing-address'],
            ['email', 'user@example.invalid']
        ]);
        $CustomerWithoutAddressMail->method('getAddress')->willThrowException(new QUI\Exception('missing'));
        $CustomerWithoutAddressMail->method('getStandardAddress')->willReturn($EmptyAddress);

        self::assertSame(
            'user@example.invalid',
            Utils::getInstance()->getEmailByCustomer($CustomerWithoutAddressMail)
        );
    }

    public function testContactAndCustomerObjectHelpersReturnFalseWithoutData(): void
    {
        $Customer = $this->createMock(QUI\Interfaces\Users\User::class);
        $Customer->method('getAttribute')->willReturn(false);
        $Customer->method('getUUID')->willReturn('phpunit-missing-user');
        $Customer->method('getStandardAddress')->willThrowException(new QUI\Exception('missing'));

        self::assertFalse(Utils::getInstance()->getContactEmailByCustomer($Customer));
        self::assertFalse(Utils::getInstance()->getContactPersonAddress($Customer));
        self::assertFalse($this->invoke('getEmailByCustomerObject', [$Customer]));
        self::assertFalse($this->invoke('getEmailByStandardAddress', [$Customer]));
    }

    private function invoke(string $method, array $arguments): mixed
    {
        return (new ReflectionMethod(Utils::class, $method))->invoke(Utils::getInstance(), ...$arguments);
    }
}
