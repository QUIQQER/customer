<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\DemoData;

use PHPUnit\Framework\TestCase;
use QUI\Exception;
use QUI\ERP\DemoData\DTO\DemoDataCreationContext;
use QUI\ERP\DemoData\DTO\DemoDataReferenceCollection;
use QUI\ERP\DemoData\DTO\DemoDataReference;
use QUI\ERP\Customer\Customers;
use QUI\ERP\Customer\NumberRange;
use QUI\Interfaces\Users\User;

final class CustomerDemoDataCreatorTest extends TestCase
{
    public function testReturnsReferencesForCreatedCustomers(): void
    {
        $privateCustomer = $this->createMock(User::class);
        $privateCustomer->method('getUUID')->willReturn('private-customer-uuid');

        $businessCustomer = $this->createMock(User::class);
        $businessCustomer->method('getUUID')->willReturn('business-customer-uuid');

        $customerNumbers = [];
        $addresses = [];
        $customers = $this->createMock(Customers::class);
        $customers->expects($this->exactly(10))
            ->method('createCustomer')
            ->with(
                $this->callback(static function (int|string $customerNumber) use (&$customerNumbers): bool {
                    $customerNumbers[] = $customerNumber;

                    return true;
                }),
                $this->callback(static function (array $address) use (&$addresses): bool {
                    $addresses[] = $address;

                    return true;
                })
            )
            ->willReturnOnConsecutiveCalls(
                $privateCustomer,
                $businessCustomer,
                $privateCustomer,
                $privateCustomer,
                $privateCustomer,
                $privateCustomer,
                $privateCustomer,
                $privateCustomer,
                $privateCustomer,
                $privateCustomer
            );

        $numberRange = $this->createMock(NumberRange::class);
        $numberRange->expects($this->exactly(10))
            ->method('getNextCustomerNo')
            ->willReturnOnConsecutiveCalls(1000, 1001, 1002, 1003, 1004, 1005, 1006, 1007, 1008, 1009);

        $creator = new CustomerDemoDataCreator($customers, $numberRange);
        $demoData = $creator->createDemoData(new DemoDataCreationContext(new DemoDataReferenceCollection()));

        self::assertSame(range(1000, 1009), $customerNumbers);
        self::assertSame('Mr', $addresses[0]['salutation']);
        self::assertSame('Mrs.', $addresses[1]['salutation']);
        self::assertSame([], $creator->getDependencies());
        self::assertSame('customer', $demoData->all()[0]->entityType);
        self::assertSame('private-customer-uuid', $demoData->all()[0]->entityUuid);
        self::assertSame('private_customer', $demoData->all()[0]->referenceKey);
        self::assertSame('business-customer-uuid', $demoData->all()[1]->entityUuid);
        self::assertSame('business_customer', $demoData->all()[1]->referenceKey);
        self::assertCount(10, $demoData->all());
        self::assertSame('customer_10', $demoData->all()[9]->referenceKey);
    }

    public function testDeleteRejectsReferencesOfAnotherEntityType(): void
    {
        $creator = new CustomerDemoDataCreator($this->createMock(Customers::class));
        $references = new DemoDataReferenceCollection([
            'quiqqer.customer' => [
                new DemoDataReference('quiqqer.customer', 'invoice', 'uuid', null, [])
            ]
        ]);

        $this->expectException(Exception::class);
        $creator->deleteDemoData($references);
    }
}
