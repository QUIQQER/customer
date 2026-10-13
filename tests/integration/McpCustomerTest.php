<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\Tests\Integration;

use Mcp\Schema\Result\CallToolResult;
use Mcp\Server\Builder;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\AI\MCP\Server;
use QUI\AI\MCP\ToolHelper;
use QUI\ERP\Customer\MCP\Provider;
use QUI\ERP\Customer\NumberRange;
use QUI\Interfaces\Users\User;
use QUI\Permissions\Permission;
use ReflectionMethod;
use ReflectionProperty;

final class McpCustomerTest extends TestCase
{
    /** @var list<string> */
    private array $createdUsers = [];
    /** @var array<string, array{exists: bool, value: mixed}> */
    private array $configSnapshot = [];
    private mixed $previousRequestUser;
    private mixed $previousSessionUser;
    private mixed $previousPermissionUser;
    private int $customerNumber;

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousRequestUser = (new ReflectionProperty(Server::class, 'RequestUser'))->getValue();
        $this->previousSessionUser = (new ReflectionProperty(QUI::getUsers(), 'Session'))->getValue(QUI::getUsers());
        $this->previousPermissionUser = (new ReflectionProperty(Permission::class, 'User'))->getValue();
        $this->useActor(QUI::getUsers()->getSystemUser());
        $Config = QUI::getPackage('quiqqer/customer')->getConfig();
        self::assertInstanceOf(QUI\Config::class, $Config);

        foreach (['nextCustomerNo', 'customerNoPrefix', 'groupId'] as $key) {
            $this->configSnapshot[$key] = [
                'exists' => $Config->existValue('customer', $key),
                'value' => $Config->getValue('customer', $key)
            ];
        }

        $this->customerNumber = random_int(840000000, 849999998);
        $Config->set('customer', 'nextCustomerNo', $this->customerNumber);
        $Config->set('customer', 'customerNoPrefix', 'PHPUNIT-MCP-');
        $Config->save();
    }

    protected function tearDown(): void
    {
        try {
            $this->useActor(QUI::getUsers()->getSystemUser());

            foreach (array_reverse($this->createdUsers) as $uuid) {
                QUI::getDataBaseConnection()->delete(QUI\Permissions\Manager::table() . '2users', [
                    'user_id' => $uuid
                ]);
                QUI::getUsers()->deleteUser($uuid);
            }

            $Config = QUI::getPackage('quiqqer/customer')->getConfig();
            self::assertInstanceOf(QUI\Config::class, $Config);

            foreach ($this->configSnapshot as $key => $snapshot) {
                if ($snapshot['exists']) {
                    $Config->set('customer', $key, $snapshot['value']);
                } else {
                    $Config->del('customer', $key);
                }
            }

            $Config->save();
        } finally {
            (new ReflectionProperty(Server::class, 'RequestUser'))->setValue(null, $this->previousRequestUser);
            (new ReflectionProperty(QUI::getUsers(), 'Session'))->setValue(QUI::getUsers(), $this->previousSessionUser);
            (new ReflectionProperty(Permission::class, 'User'))->setValue(null, $this->previousPermissionUser);
            parent::tearDown();
        }
    }

    public function testCustomerLifecycleThroughRegisteredTools(): void
    {
        $tools = $this->tools();
        self::assertSame([
            'quiqqer_customer_options',
            'quiqqer_customer_search',
            'quiqqer_customer_get',
            'quiqqer_customer_create',
            'quiqqer_customer_update',
            'quiqqer_customer_comments_list',
            'quiqqer_customer_history_list',
            'quiqqer_customer_comment_add',
            'quiqqer_customer_comment_update',
            'quiqqer_customer_open_items'
        ], array_keys($tools));
        $customer = $this->createCustomer($tools);
        self::assertSame((string)$this->customerNumber, $customer['customerNo']);
        self::assertSame('MCP Fixture GmbH', $customer['address']['company']);
        self::assertSame(14, $customer['effectivePaymentTerm']);
        self::assertSame(['contact@example.invalid'], $customer['contactPerson']['mails']);
        self::assertSame(2.5, $customer['discount']);
        self::assertSame($this->customerNumber + 1, (new NumberRange())->getNextCustomerNo());

        $byNumber = $tools['quiqqer_customer_get']['handler']('', $customer['prefixedNumber']);
        self::assertSame($customer['uuid'], $byNumber['uuid']);
        $byId = $tools['quiqqer_customer_get']['handler']((string)$customer['id']);
        self::assertSame($customer['uuid'], $byId['uuid']);
        $search = $tools['quiqqer_customer_search']['handler']($customer['prefixedNumber']);
        self::assertSame(1, $search['total']);
        self::assertSame($customer['uuid'], $search['customers'][0]['user_uuid']);
        $nextPage = $tools['quiqqer_customer_search']['handler']($customer['prefixedNumber'], 1, 1);
        self::assertSame(1, $nextPage['total']);
        self::assertSame([], $nextPage['customers']);

        $updated = $tools['quiqqer_customer_update']['handler']($customer['uuid'], [
            'customerNo' => $this->customerNumber + 1,
            'paymentTerm' => 0,
            'vatId' => 'DE123456789',
            'address' => ['city' => 'Berlin'],
            'contactPerson' => ['lastname' => 'Changed'],
            'deliveryAddress' => ['city' => 'Hamburg', 'country' => 'DE']
        ]);
        self::assertIsArray($updated);
        self::assertSame('Berlin', $updated['address']['city']);
        self::assertSame($customer['address']['company'], $updated['address']['company']);
        self::assertSame($customer['address']['mails'], $updated['address']['mails']);
        self::assertSame('Changed', $updated['contactPerson']['lastname']);
        self::assertSame($customer['contactPerson']['addressId'], $updated['contactPerson']['addressId']);
        self::assertSame('Hamburg', $updated['deliveryAddress']['city']);
        self::assertSame(0, $updated['effectivePaymentTerm']);
        self::assertSame('DE123456789', $updated['vatId']);
        self::assertSame($this->customerNumber + 2, (new NumberRange())->getNextCustomerNo());

        $cleared = $tools['quiqqer_customer_update']['handler']($customer['uuid'], [
            'address' => ['mails' => [], 'phones' => []],
            'paymentTerm' => null,
            'contactPerson' => null
        ]);
        self::assertSame([], $cleared['address']['mails']);
        self::assertSame([], $cleared['address']['phones']);
        self::assertSame('', $cleared['email']);
        self::assertSame('', $cleared['paymentTerm']);
        self::assertNull($cleared['contactPerson']);
        $restoredContact = $tools['quiqqer_customer_update']['handler']($customer['uuid'], [
            'contactPerson' => ['addressId' => $customer['contactPerson']['addressId']]
        ]);
        self::assertSame($customer['contactPerson']['addressId'], $restoredContact['contactPerson']['addressId']);
    }

    public function testRejectedDataDoesNotChangeTheCustomerOrNumberRange(): void
    {
        $tools = $this->tools();
        $customer = $this->createCustomer($tools);
        $nextNumber = (new NumberRange())->getNextCustomerNo();

        foreach (\QUI\ERP\Customer\Tests\Unit\McpCustomerDataTest::invalidData() as [$data]) {
            $this->assertError($tools['quiqqer_customer_create']['handler']($data));
            $this->assertError($tools['quiqqer_customer_update']['handler']($customer['uuid'], $data));
        }

        $invalidSearchArguments = [
            [null, 20, 0],
            [[], 20, 0],
            ['', '20', 0],
            ['', 0, 0],
            ['', 101, 0],
            ['', 20, -1],
            ['', 20, true]
        ];

        foreach ($invalidSearchArguments as $arguments) {
            $this->assertError($tools['quiqqer_customer_search']['handler'](...$arguments));
        }

        foreach ([null, [], true, 123, ''] as $identifier) {
            $this->assertError($tools['quiqqer_customer_get']['handler']($identifier));
            $this->assertError($tools['quiqqer_customer_update']['handler']($identifier, ['paymentTerm' => 7]));
        }

        $this->assertError($tools['quiqqer_customer_get']['handler']($customer['uuid'], $customer['customerNo']));
        $this->assertError($tools['quiqqer_customer_create']['handler']([
            'customerNo' => $this->customerNumber,
            'referenceCode' => 'duplicate'
        ]));
        $this->assertError($tools['quiqqer_customer_create']['handler']([
            'contactPerson' => ['addressId' => $customer['contactPerson']['addressId']]
        ]));
        self::assertSame($customer, $tools['quiqqer_customer_get']['handler']($customer['uuid']));
        self::assertSame($nextNumber, (new NumberRange())->getNextCustomerNo());
    }

    public function testNonCustomersAndForeignContactAddressesAreRejected(): void
    {
        $tools = $this->tools();
        $customer = $this->createCustomer($tools);
        $Other = QUI::getUsers()->createChild('phpunit-customer-mcp-other-' . uniqid());
        $this->createdUsers[] = (string)$Other->getUUID();
        $ForeignAddress = $Other->addAddress();
        self::assertInstanceOf(QUI\Users\Address::class, $ForeignAddress);
        $this->assertError($tools['quiqqer_customer_get']['handler']($Other->getUUID()));
        $this->assertError($tools['quiqqer_customer_update']['handler']($Other->getUUID(), ['paymentTerm' => 7]));
        $this->assertError($tools['quiqqer_customer_update']['handler']($customer['uuid'], [
            'referenceCode' => 'must-not-change',
            'address' => ['city' => 'must-not-change'],
            'contactPerson' => ['addressId' => $ForeignAddress->getUUID()]
        ]));
        self::assertSame($customer, $tools['quiqqer_customer_get']['handler']($customer['uuid']));
        $search = $tools['quiqqer_customer_search']['handler']($Other->getUsername());
        self::assertSame([], $search['customers']);
    }

    public function testPermissionsAreCheckedAtRegistrationAndEveryInvocation(): void
    {
        $tools = $this->tools();
        $customer = $this->createCustomer($tools);
        $Agent = QUI::getUsers()->createChild('phpunit-customer-mcp-agent-' . uniqid());
        $this->createdUsers[] = (string)$Agent->getUUID();
        self::assertFalse($Agent->isSU());
        $this->setActorPermissions($Agent, [Provider::PERMISSION => 1, 'quiqqer.customer.view' => 1]);
        $this->useActor($Agent);
        self::assertCount(10, $this->tools());
        self::assertSame($customer['uuid'], $tools['quiqqer_customer_get']['handler']($customer['uuid'])['uuid']);
        $this->assertError($tools['quiqqer_customer_create']['handler'](['referenceCode' => 'denied']));
        $this->assertError($tools['quiqqer_customer_update']['handler']($customer['uuid'], ['paymentTerm' => 7]));
        $this->assertError($tools['quiqqer_customer_comment_add']['handler']($customer['uuid'], 'denied'));
        self::assertIsArray($tools['quiqqer_customer_comments_list']['handler']($customer['uuid']));
        self::assertIsArray($tools['quiqqer_customer_history_list']['handler']($customer['uuid']));
        $this->assertError($tools['quiqqer_customer_open_items']['handler']($customer['uuid']));

        $this->setActorPermissions($Agent, [Provider::PERMISSION => 1]);
        $this->assertError($tools['quiqqer_customer_get']['handler']($customer['uuid']));
        $this->assertError($tools['quiqqer_customer_search']['handler']());
        $this->setActorPermissions($Agent, ['quiqqer.customer.view' => 1]);
        self::assertSame([], $this->tools());
        $this->assertError($tools['quiqqer_customer_get']['handler']($customer['uuid']));

        $this->setActorPermissions($Agent, [
            Provider::PERMISSION => 1,
            'quiqqer.customer.create' => 1,
            'quiqqer.customer.edit' => 1,
            'quiqqer.admin.users.create' => 1,
            'quiqqer.admin.users.edit' => 1
        ]);
        $createdByAgent = $this->createCustomer($tools);
        $updated = $tools['quiqqer_customer_update']['handler']($createdByAgent['uuid'], ['paymentTerm' => 7]);
        self::assertIsArray($updated);
        self::assertSame(7, $updated['effectivePaymentTerm']);

        $this->useActor(new QUI\Users\Nobody());
        self::assertSame([], $this->tools());
        $this->assertError($tools['quiqqer_customer_update']['handler']($createdByAgent['uuid'], ['paymentTerm' => 99]));
    }

    public function testMissingCustomerGroupFailsClosed(): void
    {
        $tools = $this->tools();
        $Config = QUI::getPackage('quiqqer/customer')->getConfig();
        self::assertInstanceOf(QUI\Config::class, $Config);
        $Config->set('customer', 'groupId', '');
        $Config->save();
        $this->assertError($tools['quiqqer_customer_search']['handler']());
        $this->assertError($tools['quiqqer_customer_create']['handler'](['paymentTerm' => 14]));
        self::assertSame($this->customerNumber, (new NumberRange())->getNextCustomerNo());
    }

    public function testCustomerCommentsAndHistory(): void
    {
        $tools = $this->tools();
        $customer = $this->createCustomer($tools);
        $message = '<img src=x onerror=alert(1)> & plain text';
        $comment = $tools['quiqqer_customer_comment_add']['handler']($customer['uuid'], $message);
        self::assertIsArray($comment);
        self::assertSame($message, $comment['message']);
        $User = QUI::getUsers()->get($customer['uuid']);
        self::assertStringNotContainsString('<img', (string)$User->getAttribute('comments'));
        $updated = $tools['quiqqer_customer_comment_update']['handler']($customer['uuid'], $comment['id'], 'Changed');
        self::assertSame('Changed', $updated['message']);
        $comments = $tools['quiqqer_customer_comments_list']['handler']($customer['uuid'], 1, 0);
        self::assertSame('Changed', $comments['comments'][0]['message']);
        self::assertSame(1, $comments['total']);
        self::assertSame([], $tools['quiqqer_customer_comments_list']['handler']($customer['uuid'], 1, 1)['comments']);
        \QUI\ERP\Customer\Customers::getInstance()->addHistoryToUser($User, 'PHPUNIT MCP history');
        $history = $tools['quiqqer_customer_history_list']['handler']($customer['uuid']);
        self::assertContains('PHPUNIT MCP history', array_column($history['history'], 'message'));

        foreach ([null, [], true, ''] as $value) {
            $this->assertError($tools['quiqqer_customer_comment_add']['handler']($customer['uuid'], $value));
            $this->assertError($tools['quiqqer_customer_comment_update']['handler']($customer['uuid'], $value, 'Denied'));
        }

        $this->assertError($tools['quiqqer_customer_comment_update']['handler']($customer['uuid'], 'missing', 'Denied'));
        $this->assertError($tools['quiqqer_customer_comments_list']['handler']($customer['uuid'], '20'));
        $this->assertError($tools['quiqqer_customer_history_list']['handler']($customer['uuid'], 20, -1));
        self::assertSame(1, $tools['quiqqer_customer_comments_list']['handler']($customer['uuid'])['total']);
    }

    public function testCustomerOpenItemsReadAndValidation(): void
    {
        $tools = $this->tools();
        $customer = $this->createCustomer($tools);
        $result = $tools['quiqqer_customer_open_items']['handler']($customer['uuid']);
        self::assertIsArray($result);
        self::assertSame(0, $result['total']);
        self::assertSame([], $result['items']);
        self::assertSame([], $result['totalsByCurrency']);
        self::assertSame(20, $result['limit']);

        $invalidArguments = [
            [$customer['uuid'], null],
            [$customer['uuid'], 'eur'],
            [$customer['uuid'], 'BAD-CURRENCY'],
            [$customer['uuid'], '', []],
            [$customer['uuid'], '', '', 'false'],
            [$customer['uuid'], '', '', false, 101],
            [$customer['uuid'], '', '', false, 20, -1],
            ['missing-customer']
        ];

        foreach ($invalidArguments as $arguments) {
            $this->assertError($tools['quiqqer_customer_open_items']['handler'](...$arguments));
        }
    }

    public function testMasterDataSelectionsAndSemanticValidation(): void
    {
        $tools = $this->tools();
        $customer = $this->createCustomer($tools);
        $options = $tools['quiqqer_customer_options']['handler']();
        self::assertIsArray($options);
        self::assertContains('DE', $options['countries']);
        self::assertContains('en', $options['languages']);
        $updated = $tools['quiqqer_customer_update']['handler']($customer['uuid'], [
            'language' => 'en',
            'website' => 'https://example.invalid/customer',
            'paymentMethod' => null,
            'shippingType' => null
        ]);
        self::assertSame('en', $updated['language']);
        self::assertSame('', $updated['paymentMethod']);
        self::assertSame('', $updated['shippingType']);

        $invalidData = [
            ['language' => 'invalid-language'],
            ['website' => 'javascript:alert(1)'],
            ['website' => 'https://user:password@example.invalid'],
            ['website' => 'not a website'],
            ['address' => ['country' => 'NOT-A-COUNTRY']],
            ['deliveryAddress' => ['country' => 'INVALID']],
            ['contactPerson' => ['country' => 'INVALID']],
            ['paymentMethod' => 2147483647],
            ['shippingType' => 2147483647]
        ];

        foreach ($invalidData as $data) {
            $this->assertError($tools['quiqqer_customer_update']['handler']($customer['uuid'], $data));
            $this->assertError($tools['quiqqer_customer_create']['handler']($data));
        }

        self::assertSame($updated, $tools['quiqqer_customer_get']['handler']($customer['uuid']));

        if ($options['paymentMethods'] !== []) {
            $paymentId = (int)$options['paymentMethods'][0]['id'];
            $selected = $tools['quiqqer_customer_update']['handler']($customer['uuid'], ['paymentMethod' => $paymentId]);
            self::assertSame($paymentId, $selected['paymentMethod']);
        }

        if ($options['shippingTypes'] !== []) {
            $shippingId = (int)$options['shippingTypes'][0]['id'];
            $selected = $tools['quiqqer_customer_update']['handler']($customer['uuid'], ['shippingType' => $shippingId]);
            self::assertSame($shippingId, $selected['shippingType']);
        }
    }

    public function testFailedWriteRollsBackAndRefreshesCachedCustomerData(): void
    {
        $tools = $this->tools();
        $customer = $this->createCustomer($tools);
        $User = QUI::getUsers()->get($customer['uuid']);
        $User->setAttribute('quiqqer.delivery.address', 'missing-address');
        $User->save();
        $nextNumber = (new NumberRange())->getNextCustomerNo();
        $this->assertError($tools['quiqqer_customer_update']['handler']($customer['uuid'], [
            'customerNo' => $nextNumber + 1,
            'referenceCode' => 'must-roll-back',
            'address' => ['city' => 'must-roll-back']
        ]));
        $User->setAttribute('quiqqer.delivery.address', '');
        $User->save();
        $restored = $tools['quiqqer_customer_get']['handler']($customer['uuid']);
        self::assertIsArray($restored);
        self::assertSame($customer['customerNo'], $restored['customerNo']);
        self::assertSame($customer['referenceCode'], $restored['referenceCode']);
        self::assertSame($customer['address']['city'], $restored['address']['city']);
        self::assertSame($nextNumber, (new NumberRange())->getNextCustomerNo());
    }

    /** @return array<string, array<string, mixed>> */
    private function tools(): array
    {
        $Builder = new Builder();
        (new Provider())->register($Builder);
        $registered = (new ReflectionProperty($Builder, 'tools'))->getValue($Builder);
        $tools = [];

        foreach ($registered as $tool) {
            $tools[$tool['name']] = $tool;
        }

        return $tools;
    }

    /**
     * @param array<string, array<string, mixed>> $tools
     * @return array<string, mixed>
     */
    private function createCustomer(array $tools): array
    {
        $email = 'phpunit-customer-mcp-' . uniqid() . '@example.invalid';
        $customer = $tools['quiqqer_customer_create']['handler']([
            'paymentTerm' => 14,
            'referenceCode' => 'PHPUNIT-MCP',
            'discount' => 2.5,
            'address' => [
                'company' => 'MCP Fixture GmbH',
                'firstname' => 'PHPUnit',
                'lastname' => 'Customer',
                'city' => 'Aachen',
                'country' => 'DE',
                'mails' => [$email],
                'phones' => [['type' => 'tel', 'no' => '+49 241 12345']]
            ],
            'contactPerson' => [
                'firstname' => 'Test',
                'lastname' => 'Contact',
                'mails' => ['contact@example.invalid']
            ]
        ]);
        $diagnostic = $customer;

        if ($customer instanceof CallToolResult && method_exists(ToolHelper::class, 'getLastException')) {
            $Exception = ToolHelper::getLastException();
            $diagnostic = $Exception instanceof \Throwable ? $Exception->getMessage() : 'Unknown MCP error';

            if ($Exception instanceof QUI\Exception) {
                $diagnostic .= ' Permission: ' . (string)$Exception->getAttribute('permission');
            }
        }

        self::assertIsArray($customer, var_export($diagnostic, true));
        $this->createdUsers[] = $customer['uuid'];

        return $customer;
    }

    private function useActor(User $User): void
    {
        (new ReflectionProperty(Server::class, 'RequestUser'))->setValue(null, $User);
        (new ReflectionProperty(QUI::getUsers(), 'Session'))->setValue(QUI::getUsers(), $User);
        Permission::setUser($User);
    }

    /** @param array<string, int> $permissions */
    private function setActorPermissions(User $User, array $permissions): void
    {
        $Connection = QUI::getDataBaseConnection();
        $table = QUI\Permissions\Manager::table() . '2users';
        $Connection->delete($table, ['user_id' => $User->getUUID()]);
        $Connection->insert($table, [
            'user_id' => $User->getUUID(),
            'permissions' => json_encode($permissions, JSON_THROW_ON_ERROR)
        ]);
        $Manager = QUI::getPermissionManager();
        $cacheKey = (new ReflectionMethod($Manager, 'getDataCacheId'))->invoke($Manager, $User);

        foreach (['dataCache', 'permissionsCache'] as $property) {
            $Property = new ReflectionProperty($Manager, $property);
            $cache = $Property->getValue($Manager);
            unset($cache[$cacheKey]);
            $Property->setValue($Manager, $cache);
        }
    }

    private function assertError(mixed $result): void
    {
        self::assertInstanceOf(CallToolResult::class, $result);
        self::assertTrue($result->isError);
    }
}
