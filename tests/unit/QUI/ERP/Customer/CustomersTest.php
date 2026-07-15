<?php

namespace QUI\ERP\Customer;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

class CustomersTest extends TestCase
{
    public function testSetDefaultPaymentMethodSetsConfiguredPayment(): void
    {
        $Customers = (new ReflectionClass(Customers::class))->newInstanceWithoutConstructor();
        $Method = new ReflectionMethod(Customers::class, 'setDefaultPaymentMethod');
        $Method->setAccessible(true);
        $User = $this->createMock(\QUI\Interfaces\Users\User::class);

        $User->expects($this->once())
            ->method('setAttribute')
            ->with('quiqqer.erp.standard.payment', '42');

        $Method->invoke($Customers, $User, '42');
    }

    /**
     * @dataProvider emptyDefaultPaymentMethodProvider
     */
    public function testSetDefaultPaymentMethodKeepsCurrentBehaviorForEmptyValues(mixed $value): void
    {
        $Customers = (new ReflectionClass(Customers::class))->newInstanceWithoutConstructor();
        $Method = new ReflectionMethod(Customers::class, 'setDefaultPaymentMethod');
        $Method->setAccessible(true);
        $User = $this->createMock(\QUI\Interfaces\Users\User::class);

        $User->expects($this->never())->method('setAttribute');

        $Method->invoke($Customers, $User, $value);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function emptyDefaultPaymentMethodProvider(): array
    {
        return [
            'null' => [null],
            'empty string' => [''],
            'zero' => [0],
            'false' => [false]
        ];
    }

    public function testNormalizeCommentsDataKeepsOnlyListEntriesWithStringKeys(): void
    {
        $Customers = (new ReflectionClass(Customers::class))->newInstanceWithoutConstructor();
        $Method = new \ReflectionMethod(Customers::class, 'normalizeCommentsData');
        $Method->setAccessible(true);

        $result = $Method->invoke($Customers, [
            [
                'id' => 1,
                'message' => 'First comment',
                0 => 'ignored numeric key'
            ],
            'invalid comment entry',
            [
                'id' => 2,
                'message' => 'Second comment'
            ]
        ]);

        $this->assertSame([
            [
                'id' => 1,
                'message' => 'First comment'
            ],
            [
                'id' => 2,
                'message' => 'Second comment'
            ]
        ], $result);
    }

    public function testNormalizeCommentsDataReturnsEmptyListForNonArrayInput(): void
    {
        $Customers = (new ReflectionClass(Customers::class))->newInstanceWithoutConstructor();
        $Method = new \ReflectionMethod(Customers::class, 'normalizeCommentsData');
        $Method->setAccessible(true);

        $this->assertSame([], $Method->invoke($Customers, null));
        $this->assertSame([], $Method->invoke($Customers, 'invalid'));
    }
}
