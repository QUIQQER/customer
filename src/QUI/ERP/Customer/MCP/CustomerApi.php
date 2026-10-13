<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\MCP;

use QUI;
use QUI\AI\MCP\Server;
use QUI\ERP\Customer\Customers;
use QUI\ERP\Customer\NumberRange;
use QUI\ERP\Customer\Search;
use QUI\ERP\Customer\Utils;
use QUI\Interfaces\Users\User;
use QUI\Permissions\Permission;
use QUI\Users\Address;
use Throwable;

/**
 * Customer operations for the permission-checked MCP provider.
 */
final class CustomerApi
{
    /** @return array<string, mixed> */
    public function search(mixed $query, mixed $limit, mixed $offset): array
    {
        CustomerData::validate($query, ['type' => 'string', 'maxLength' => 255], 'query');
        CustomerData::validate($limit, ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'limit');
        CustomerData::validate(
            $offset,
            ['type' => 'integer', 'minimum' => 0, 'maximum' => 2147483647],
            'offset'
        );

        // Fail closed if customer group setup is missing; Search otherwise falls back to all users.
        Customers::getInstance()->getCustomerGroupId();
        $Search = new Search();
        $Search->searchOnlyInCustomer();
        $Search->setFilter('search', trim($query));
        $Search->setFilter('customerId', '1');
        $Search->setFilter('firstname', '1');
        $Search->setFilter('lastname', '1');
        $Search->setFilter('company', '1');
        $Search->setFilter('email', '1');
        $Search->limit($offset, $limit);
        $Search->order('customerId');
        $grid = $Search->searchForGrid();

        return [
            'total' => (int)($grid['total'] ?? 0),
            'limit' => $limit,
            'offset' => $offset,
            'customers' => $grid['data'] ?? []
        ];
    }

    /** @return array<string, mixed> */
    public function get(mixed $customerId, mixed $customerNo): array
    {
        $this->validateIdentifier($customerId, true);
        $this->validateIdentifier($customerNo, true);

        if (($customerId === '') === ($customerNo === '')) {
            throw new QUI\Exception('Supply exactly one of customerId or customerNo.', 400);
        }

        if ($customerNo !== '') {
            $prefix = (new NumberRange())->getCustomerNoPrefix();

            if ($prefix !== '' && str_starts_with($customerNo, $prefix)) {
                $customerNo = substr($customerNo, strlen($prefix));
            }

            if (!ctype_digit($customerNo)) {
                throw new QUI\Exception('Customer number must contain digits after its prefix.', 400);
            }

            $QueryBuilder = QUI::getQueryBuilder();
            $customerId = $QueryBuilder
                ->select('uuid')
                ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI::getUsers()::table()))
                ->where($QueryBuilder->expr()->eq(QUI\Utils\Doctrine::quoteIdentifier('customerId'), ':number'))
                ->setParameter('number', $customerNo)
                ->setMaxResults(1)
                ->executeQuery()
                ->fetchOne();

            if (!is_string($customerId)) {
                throw new QUI\Exception('Customer not found.', 404);
            }
        }

        return $this->parse($this->getCustomer($customerId));
    }

    /** @return array<string, mixed> */
    public function create(mixed $data): array
    {
        CustomerData::validate($data, CustomerData::schema());
        CustomerData::validateMasterData($data);

        if (isset($data['contactPerson']['addressId'])) {
            throw new QUI\Exception('A new customer cannot reference an existing contact address.', 400);
        }

        Customers::getInstance()->getCustomerGroupId();
        Permission::checkPermission('quiqqer.admin.users.create', Server::getRequestUser());
        Permission::checkPermission('quiqqer.admin.users.edit', Server::getRequestUser());
        $NumberRange = new NumberRange();
        $number = $data['customerNo'] ?? $NumberRange->getNextCustomerNo();
        CustomerData::validate($number, CustomerData::schema()['properties']['customerNo'], 'customerNo');
        $this->checkNumberAvailable($number);

        return QUI::getDataBaseConnection()->transactional(function () use ($number, $data): array {
            $User = Customers::getInstance()->createCustomer($number, $data['address'] ?? []);
            $this->apply($User, $data);

            return $this->parse($User);
        });
    }

    /** @return array<string, mixed> */
    public function update(mixed $customerId, mixed $data): array
    {
        $this->validateIdentifier($customerId);
        CustomerData::validate($data, CustomerData::schema());
        CustomerData::validateMasterData($data);
        $User = $this->getCustomer($customerId);
        Permission::checkPermission('quiqqer.admin.users.edit', Server::getRequestUser());

        if (isset($data['customerNo'])) {
            $this->checkNumberAvailable($data['customerNo'], (string)$User->getUUID());
        }

        if (isset($data['contactPerson']['addressId'])) {
            // Resolve ownership before changing any customer or address field.
            $User->getAddress($data['contactPerson']['addressId']);
        }

        try {
            $result = QUI::getDataBaseConnection()->transactional(function () use ($User, $data): array {
                $this->apply($User, $data);

                return $this->parse($User);
            });
        } catch (Throwable $Exception) {
            // Reload the cached user and addresses after the database rollback.
            $User->refresh();
            throw $Exception;
        }

        if (isset($data['customerNo'])) {
            $NumberRange = new NumberRange();

            if ($data['customerNo'] >= $NumberRange->getNextCustomerNo()) {
                $NumberRange->setRange($data['customerNo'] + 1);
            }
        }

        return $result;
    }

    private function validateIdentifier(mixed $value, bool $allowEmpty = false): void
    {
        CustomerData::validate($value, [
            'type' => 'string',
            'minLength' => $allowEmpty ? 0 : 1,
            'maxLength' => 64
        ], 'customer identifier');

        if ($value !== '' && trim($value) !== $value) {
            throw new QUI\Exception('Customer identifiers must not contain surrounding whitespace.', 400);
        }
    }

    public function getCustomer(mixed $customerId): QUI\Users\User
    {
        $this->validateIdentifier($customerId);
        $User = QUI::getUsers()->get($customerId);

        if (
            !$User instanceof QUI\Users\User
            || !$User->isInGroup(Customers::getInstance()->getCustomerGroupId())
            || $User->getAttribute('deleted')
        ) {
            throw new QUI\Exception('Customer not found.', 404);
        }

        return $User;
    }

    private function checkNumberAvailable(int $number, string $excludeUuid = ''): void
    {
        $QueryBuilder = QUI::getQueryBuilder();
        $quote = static fn(string $field): string => QUI\Utils\Doctrine::quoteIdentifier($field);
        $QueryBuilder
            ->select('uuid')
            ->from($quote(QUI::getUsers()::table()))
            ->where($QueryBuilder->expr()->or(
                $QueryBuilder->expr()->eq($quote('customerId'), ':number'),
                $QueryBuilder->expr()->eq('username', ':username')
            ))
            ->setParameter('number', $number)
            ->setParameter('username', (string)$number);

        if ($excludeUuid !== '') {
            $QueryBuilder->andWhere($QueryBuilder->expr()->neq('uuid', ':uuid'));
            $QueryBuilder->setParameter('uuid', $excludeUuid);
        }

        if ($QueryBuilder->setMaxResults(1)->executeQuery()->fetchOne() !== false) {
            throw new QUI\Exception('Customer number is already in use.', 409);
        }
    }

    /** @param array<string, mixed> $data */
    private function apply(User $User, array $data): void
    {
        foreach (CustomerData::ATTRIBUTES as $field => $attribute) {
            if (array_key_exists($field, $data)) {
                $value = $data[$field];

                if ($value === null || ($field === 'pricingMode' && $value === 0)) {
                    $value = '';
                }

                $User->setAttribute($attribute, $value);
            }
        }

        if (isset($data['customerNo'])) {
            if ($User->getUsername() === (string)$User->getAttribute('customerId')) {
                $User->setAttribute('username', (string)$data['customerNo']);
            }

            $User->setAttribute('customerId', $data['customerNo']);
        }

        if (isset($data['address'])) {
            $Address = $this->getMainAddress($User) ?? $this->addAddress($User);
            $this->applyAddress($Address, $data['address']);
            $User->setCompanyStatus((string)$Address->getAttribute('company') !== '');

            foreach (['firstname', 'lastname'] as $field) {
                if (array_key_exists($field, $data['address'])) {
                    $User->setAttribute($field, $data['address'][$field]);
                }
            }

            if (array_key_exists('mails', $data['address'])) {
                $User->setAttribute('email', $data['address']['mails'][0] ?? '');
            }
        }

        if (isset($data['deliveryAddress'])) {
            $addressId = (string)$User->getAttribute('quiqqer.delivery.address');
            $Address = $addressId !== '' ? $User->getAddress($addressId) : $this->addAddress($User);
            $this->applyAddress($Address, $data['deliveryAddress']);
            $User->setAttribute('quiqqer.delivery.address', $Address->getUUID());
        }

        if (array_key_exists('contactPerson', $data)) {
            $contact = $data['contactPerson'];
            $addressId = '';

            if ($contact !== null) {
                $addressId = $contact['addressId'] ?? (string)$User->getAttribute('quiqqer.erp.customer.contact.person');
                $Address = $addressId !== '' ? $User->getAddress($addressId) : $this->addAddress($User);
                unset($contact['addressId']);

                if ($contact !== []) {
                    $this->applyAddress($Address, $contact);
                }

                $addressId = $Address->getUUID();
            }

            $User->setAttribute('quiqqer.erp.customer.contact.person', $addressId);
        }

        $User->save(Server::getRequestUser());
        $User->refresh();
    }

    private function addAddress(User $User): Address
    {
        $Address = $User->addAddress([], Server::getRequestUser());

        if (!$Address instanceof Address) {
            throw new QUI\Exception('Could not create customer address.');
        }

        return $Address;
    }

    /** @param array<string, mixed> $data */
    private function applyAddress(Address $Address, array $data): void
    {
        foreach (CustomerData::ADDRESS_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if ($field === 'suffix') {
                $Address->setAddressSuffix($data[$field]);
            } else {
                $Address->setAttribute($field, $data[$field]);
            }
        }

        if (array_key_exists('mails', $data)) {
            $Address->clearMail();

            foreach ($data['mails'] as $mail) {
                $Address->addMail($mail);
            }
        }

        if (array_key_exists('phones', $data)) {
            $Address->clearPhone();

            foreach ($data['phones'] as $phone) {
                $Address->addPhone($phone);
            }
        }

        $Address->save(Server::getRequestUser());
    }

    private function getMainAddress(User $User): ?Address
    {
        $addressId = (string)$User->getAttribute('quiqqer.erp.address');

        return $addressId !== '' ? $User->getAddress($addressId) : $User->getStandardAddress();
    }

    /** @return array<string, mixed> */
    private function parse(User $User): array
    {
        $number = (string)$User->getAttribute('customerId');
        $result = [
            'id' => $User->getId(),
            'uuid' => $User->getUUID(),
            'customerNo' => $number,
            'prefixedNumber' => (new NumberRange())->getCustomerNoPrefix() . $number,
            'email' => (string)$User->getAttribute('email'),
            'firstname' => (string)$User->getAttribute('firstname'),
            'lastname' => (string)$User->getAttribute('lastname'),
            'address' => $this->parseAddress($this->getMainAddress($User)),
            'effectivePaymentTerm' => Utils::getInstance()->getPaymentTimeForUser($User->getUUID())
        ];

        foreach (CustomerData::ATTRIBUTES as $field => $attribute) {
            $result[$field] = $User->getAttribute($attribute);
        }

        $addressAttributes = [
            'contactPerson' => 'quiqqer.erp.customer.contact.person',
            'deliveryAddress' => 'quiqqer.delivery.address'
        ];

        foreach ($addressAttributes as $field => $attribute) {
            $addressId = (string)$User->getAttribute($attribute);
            $result[$field] = $addressId !== '' ? $this->parseAddress($User->getAddress($addressId)) : null;
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    private function parseAddress(?Address $Address): ?array
    {
        if ($Address === null) {
            return null;
        }

        $result = ['addressId' => $Address->getUUID()];

        foreach (CustomerData::ADDRESS_FIELDS as $field) {
            $result[$field] = $field === 'suffix'
                ? $Address->getAddressSuffix()
                : $Address->getAttribute($field);
        }

        $result['mails'] = array_values($Address->getMailList());
        $result['phones'] = array_values($Address->getPhoneList());

        return $result;
    }
}
