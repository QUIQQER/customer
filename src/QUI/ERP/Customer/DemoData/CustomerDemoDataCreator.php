<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\DemoData;

use QUI\Exception;
use QUI\ERP\Customer\Customers;
use QUI\ERP\DemoData\Contract\DemoDataCreatorInterface;
use QUI\ERP\DemoData\DTO\CreatedDemoData;
use QUI\ERP\DemoData\DTO\CreatedDemoDataCollection;
use QUI\ERP\DemoData\DTO\DemoDataCreationContext;
use QUI\Interfaces\Users\User;

final readonly class CustomerDemoDataCreator implements DemoDataCreatorInterface
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
        $privateCustomer = $this->getOrCreateCustomer(
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

        $businessCustomer = $this->getOrCreateCustomer(
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

    /**
     * @param array<string, string> $address
     */
    private function getOrCreateCustomer(int $customerId, array $address): User
    {
        try {
            return $this->customers->getCustomerByCustomerNo((string)$customerId);
        } catch (Exception $exception) {
            if ($exception->getCode() !== 404) {
                throw $exception;
            }
        }

        return $this->customers->createCustomer($customerId, $address);
    }
}
