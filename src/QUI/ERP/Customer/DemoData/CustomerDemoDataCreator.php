<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\DemoData;

use QUI\Exception;
use QUI\ERP\Customer\Customers;
use QUI\ERP\DemoData\Contract\DemoDataCreatorInterface;
use QUI\ERP\DemoData\Contract\DemoDataDeletionCreatorInterface;
use QUI\ERP\DemoData\DTO\CreatedDemoData;
use QUI\ERP\DemoData\DTO\CreatedDemoDataCollection;
use QUI\ERP\DemoData\DTO\DemoDataCreationContext;
use QUI\ERP\DemoData\DTO\DemoDataReferenceCollection;
use QUI\Interfaces\Users\User;

final readonly class CustomerDemoDataCreator implements DemoDataCreatorInterface, DemoDataDeletionCreatorInterface
{
    private const PRIVATE_CUSTOMER_ID = 100000;
    private const BUSINESS_CUSTOMER_ID = 100001;

    public function __construct(private Customers $customers)
    {
    }

    public function getDependencies(): array
    {
        return [];
    }

    public function createDemoData(DemoDataCreationContext $context): CreatedDemoDataCollection
    {
        $privateCustomer = $this->createCustomer(
            self::PRIVATE_CUSTOMER_ID,
            [
                'salutation' => 'Mr',
                'firstname' => 'Max',
                'lastname' => 'Mustermann',
                'street_no' => 'Musterstraße 1',
                'zip' => '12345',
                'city' => 'Musterstadt',
                'country' => 'DE'
            ]
        );

        $businessCustomer = $this->createCustomer(
            self::BUSINESS_CUSTOMER_ID,
            [
                'salutation' => 'Ms',
                'firstname' => 'Erika',
                'lastname' => 'Musterfrau',
                'company' => 'Muster GmbH',
                'street_no' => 'Beispielweg 2',
                'zip' => '54321',
                'city' => 'Beispielstadt',
                'country' => 'DE'
            ]
        );

        return new CreatedDemoDataCollection([
            new CreatedDemoData('customer', (string)$privateCustomer->getUUID(), 'private_customer'),
            new CreatedDemoData('customer', (string)$businessCustomer->getUUID(), 'business_customer')
        ]);
    }

    public function deleteDemoData(DemoDataReferenceCollection $demoData): void
    {
        $customerUuids = [];

        foreach ($demoData->forProvider('quiqqer.customer') as $reference) {
            if ($reference->entityType !== 'customer') {
                throw new Exception('Customer demo data reference has an invalid entity type.');
            }

            $customerUuids[$reference->entityUuid] = true;
        }

        foreach (array_keys($customerUuids) as $customerUuid) {
            \QUI::getUsers()->deleteUser($customerUuid);
        }
    }

    /**
     * @param array<string, string> $address
     */
    private function createCustomer(int $customerId, array $address): User
    {
        return $this->customers->createCustomer($customerId, $address);
    }
}
