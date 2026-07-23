<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\DemoData;

use PHPUnit\Framework\TestCase;
use QUI\ERP\DemoData\DTO\DemoDataCreationContext;
use QUI\ERP\DemoData\DTO\DemoDataReferenceCollection;
use QUI\ERP\Customer\Customers;
use QUI\Interfaces\Users\User;

final class CustomerDemoDataCreatorTest extends TestCase
{
    public function testReturnsReferencesForCreatedCustomers(): void
    {
        $privateCustomer = $this->createMock(User::class);
        $privateCustomer->method('getUUID')->willReturn('private-customer-uuid');

        $businessCustomer = $this->createMock(User::class);
        $businessCustomer->method('getUUID')->willReturn('business-customer-uuid');

        $customers = $this->createMock(Customers::class);
        $customers->expects($this->exactly(2))
            ->method('createCustomer')
            ->willReturnOnConsecutiveCalls($privateCustomer, $businessCustomer);

        $creator = new CustomerDemoDataCreator($customers);
        $demoData = $creator->createDemoData(new DemoDataCreationContext(new DemoDataReferenceCollection()));

        self::assertSame([], $creator->getDependencies());
        self::assertSame('customer', $demoData->all()[0]->entityType);
        self::assertSame('private-customer-uuid', $demoData->all()[0]->entityUuid);
        self::assertSame('private_customer', $demoData->all()[0]->referenceKey);
        self::assertSame('business-customer-uuid', $demoData->all()[1]->entityUuid);
        self::assertSame('business_customer', $demoData->all()[1]->referenceKey);
    }
}
