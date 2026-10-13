<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Customer\MCP\CustomerData;

final class McpCustomerDataTest extends TestCase
{
    #[DataProvider('invalidData')]
    public function testMalformedDataIsRejected(mixed $data): void
    {
        $this->expectException(QUI\Exception::class);
        CustomerData::validate($data, CustomerData::schema());
    }

    /** @return iterable<string, array{mixed}> */
    public static function invalidData(): iterable
    {
        yield 'JSON string' => ['{"paymentTerm":14}'];
        yield 'null' => [null];
        yield 'boolean' => [true];
        yield 'empty patch' => [[]];
        yield 'list' => [[['paymentTerm' => 14]]];
        yield 'permission escalation' => [['su' => 1]];
        yield 'group changes' => [['groups' => 'admins']];
        yield 'password changes' => [['password' => 'secret']];
        yield 'numeric string' => [['paymentTerm' => '14']];
        yield 'fractional days' => [['paymentTerm' => 1.5]];
        yield 'negative days' => [['paymentTerm' => -1]];
        yield 'huge days' => [['paymentTerm' => 3651]];
        yield 'zero customer number' => [['customerNo' => 0]];
        yield 'prefixed number on write' => [['customerNo' => 'C123']];
        yield 'oversized customer number' => [['customerNo' => PHP_INT_MAX]];
        yield 'invalid pricing mode' => [['pricingMode' => 3]];
        yield 'boolean pricing mode' => [['pricingMode' => true]];
        yield 'excessive discount' => [['discount' => 101]];
        yield 'nonfinite discount' => [['discount' => INF]];
        yield 'NaN discount' => [['discount' => NAN]];
        yield 'long text' => [['referenceCode' => str_repeat('x', 256)]];
        yield 'null byte' => [['referenceCode' => "test\0value"]];
        yield 'address string' => [['address' => 'Berlin']];
        yield 'empty address' => [['address' => []]];
        yield 'nested unknown field' => [['address' => ['userUuid' => 'someone-else']]];
        yield 'email string instead of list' => [['address' => ['mails' => 'test@example.invalid']]];
        yield 'invalid email' => [['address' => ['mails' => ['bad-email']]]];
        yield 'too many emails' => [['address' => ['mails' => array_fill(0, 21, 'test@example.invalid')]]];
        yield 'associative email list' => [['address' => ['mails' => ['first' => 'test@example.invalid']]]];
        yield 'invalid phone type' => [['contactPerson' => ['phones' => [['type' => 'email', 'no' => '123']]]]];
        yield 'missing phone type' => [['address' => ['phones' => [['no' => '123']]]]];
        yield 'blank phone' => [['address' => ['phones' => [['type' => 'tel', 'no' => ' ']]]]];
        yield 'phone foreign field' => [['address' => ['phones' => [['type' => 'tel', 'no' => '123', 'uid' => 1]]]]];
        yield 'null address' => [['address' => null]];
        yield 'empty contact ID' => [['contactPerson' => ['addressId' => '']]];
        yield 'array contact ID' => [['contactPerson' => ['addressId' => ['123']]]];
    }

    public function testZeroDaysAndExplicitClearingAreValid(): void
    {
        CustomerData::validate([
            'customerNo' => 123,
            'paymentTerm' => 0,
            'pricingMode' => 0,
            'discount' => 2.5,
            'address' => ['mails' => [], 'phones' => [], 'company' => ''],
            'contactPerson' => null
        ], CustomerData::schema());
        CustomerData::validate(['paymentTerm' => null], CustomerData::schema());
        $this->addToAssertionCount(2);
    }
}
