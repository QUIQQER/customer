<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\DemoData;

use QUI\Exception;
use QUI\ERP\Customer\Customers;
use QUI\ERP\DemoData\Contract\DemoDataCreatorInterface;
use QUI\ERP\DemoData\DTO\CreatedDemoData;
use QUI\ERP\DemoData\DTO\CreatedDemoDataCollection;
use QUI\ERP\DemoData\DTO\DemoDataCreationContext;
use QUI\ERP\DemoData\DTO\DemoDataReferenceCollection;
use QUI\Interfaces\Users\User;

final readonly class CustomerDemoDataCreator implements DemoDataCreatorInterface
{
    private const CUSTOMER_NUMBER_MIN = 100000;
    private const CUSTOMER_NUMBER_MAX = 999999;
    private const CUSTOMER_NUMBER_ATTEMPTS = 10;

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
    private function createCustomer(array $address): User
    {
        for ($attempt = 0; $attempt < self::CUSTOMER_NUMBER_ATTEMPTS; $attempt++) {
            $customerNumber = random_int(self::CUSTOMER_NUMBER_MIN, self::CUSTOMER_NUMBER_MAX);

            try {
                $this->customers->getCustomerByCustomerNo((string)$customerNumber);
            } catch (Exception $exception) {
                if ($exception->getCode() === 404) {
                    return $this->customers->createCustomer($customerNumber, $address);
                }

                throw $exception;
            }
        }

        throw new Exception('Could not generate a free demo data customer number.');
    }
}
