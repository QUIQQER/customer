<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\MCP;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;

final class AddressTools
{
    public function __construct(private readonly Provider $Provider, private readonly CustomerApi $Api)
    {
    }

    public function register(Builder $Builder): void
    {
        $Builder->addTool(
            fn(mixed $customerId, mixed $limit = 20, mixed $offset = 0): array | CallToolResult =>
                $this->Provider->execute('view', fn(): array => $this->get($customerId, $limit, $offset)),
            name: 'quiqqer_customer_addresses_list',
            description: 'List all addresses owned by the customer, including standard, ERP, delivery and '
                . 'contact-person assignments. Use returned addressId values when selecting a contact person.',
            inputSchema: [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['customerId'],
                'properties' => [
                    'customerId' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 64],
                    'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 20],
                    'offset' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647, 'default' => 0]
                ]
            ]
        );
    }

    /** @return array<string, mixed> */
    private function get(mixed $customerId, mixed $limit, mixed $offset): array
    {
        CustomerData::validate($limit, ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'limit');
        CustomerData::validate($offset, ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647], 'offset');
        $User = $this->Api->getCustomer($customerId);
        $standardId = $User->getStandardAddress()->getUUID();
        $entries = [];

        foreach ($User->getAddressList() as $Address) {
            $entry = $this->Api->parseAddress($Address);

            if ($entry === null) {
                continue;
            }

            $entry['standard'] = $Address->getUUID() === $standardId;
            $entry['erp'] = $Address->getUUID() === $User->getAttribute('quiqqer.erp.address');
            $entry['delivery'] = $Address->getUUID() === $User->getAttribute('quiqqer.delivery.address');
            $entry['contactPerson'] = $Address->getUUID() === $User->getAttribute('quiqqer.erp.customer.contact.person');
            $entries[] = $entry;
        }

        usort($entries, static fn(array $a, array $b): int => strcmp($a['addressId'], $b['addressId']));

        return [
            'total' => count($entries),
            'limit' => $limit,
            'offset' => $offset,
            'addresses' => array_slice($entries, $offset, $limit)
        ];
    }
}
