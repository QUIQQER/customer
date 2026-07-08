<?php

namespace QUI\ERP\Customer;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

class CustomersTest extends TestCase
{
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
