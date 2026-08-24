<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Customer\BackendSearchProvider;
use QUI\ERP\Customer\Customers;
use QUI\ERP\Customer\EventHandler;
use QUI\ERP\Customer\Search;
use QUI\ERP\Customer\Utils;
use QUI\Interfaces\Users\User as UserInterface;
use ReflectionProperty;
use ReflectionMethod;
use Throwable;

final class CustomerLifecycleTest extends TestCase
{
    private int $customerNumber;
    private ?string $customerUuid = null;
    private ?UserInterface $previousSessionUser = null;
    private mixed $previousNextCustomerNumber = null;
    private bool $nextCustomerNumberExisted = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->previousSessionUser = $this->replaceSessionUser(QUI::getUsers()->getSystemUser());
        $this->customerNumber = random_int(880000000, 889999999);
        $this->cleanupCustomerNumber();

        $Config = QUI::getPackage('quiqqer/customer')->getConfig();

        if (!$Config instanceof QUI\Config) {
            self::markTestSkipped('The customer package configuration is not available.');
        }

        $this->nextCustomerNumberExisted = $Config->existValue('customer', 'nextCustomerNo');
        $this->previousNextCustomerNumber = $Config->getValue('customer', 'nextCustomerNo');
        $Config->set('customer', 'nextCustomerNo', 999999999);
        $Config->save();
    }

    protected function tearDown(): void
    {
        if ($this->customerUuid !== null) {
            try {
                QUI::getUsers()->deleteUser($this->customerUuid);
            } catch (Throwable) {
            }
        }

        $this->cleanupCustomerNumber();

        try {
            $Config = QUI::getPackage('quiqqer/customer')->getConfig();

            if ($Config instanceof QUI\Config) {
                if ($this->nextCustomerNumberExisted) {
                    $Config->set('customer', 'nextCustomerNo', $this->previousNextCustomerNumber);
                } else {
                    $Config->del('customer', 'nextCustomerNo');
                }

                $Config->save();
            }
        } catch (Throwable) {
        }

        if ($this->previousSessionUser !== null) {
            $this->replaceSessionUser($this->previousSessionUser);
        }

        parent::tearDown();
    }

    public function testCustomerCanBeCreatedFoundSearchedAndDocumented(): void
    {
        $Customers = Customers::getInstance();
        $User = $Customers->createCustomer($this->customerNumber, [
            'salutation' => 'mr',
            'firstname' => 'PHPUnit',
            'lastname' => 'Customer',
            'company' => 'PHPUnit Customer GmbH',
            'street_no' => 'Test Street 42',
            'zip' => '52062',
            'city' => 'Aachen',
            'country' => 'DE',
            'suffix' => 'Headquarters'
        ], [$Customers->getCustomerGroupId()]);
        $this->customerUuid = $User->getUUID();

        self::assertSame($this->customerNumber, $User->getAttribute('customerId'));
        self::assertSame('PHPUnit', $User->getAttribute('firstname'));
        self::assertSame('Customer', $User->getAttribute('lastname'));
        self::assertTrue($User->isInGroup($Customers->getCustomerGroupId()));
        self::assertSame($User->getUUID(), $Customers->getCustomerByCustomerNo(
            (string)$this->customerNumber
        )->getUUID());

        $Address = $User->getStandardAddress();
        self::assertInstanceOf(QUI\Users\Address::class, $Address);
        self::assertSame('PHPUnit Customer GmbH', $Address->getAttribute('company'));
        self::assertSame('Aachen', $Address->getAttribute('city'));

        $Search = new Search();
        $Search->searchInAllGroups();
        $Search->setFilter('search', (string)$this->customerNumber);
        $result = $Search->search();
        self::assertNotEmpty($result);
        self::assertContains($User->getUUID(), array_column($result, 'user_uuid'));

        $grid = $Search->searchForGrid();
        self::assertGreaterThanOrEqual(1, $grid['total']);
        self::assertContains($User->getUUID(), array_column($grid['data'], 'user_uuid'));

        $SingletonSearch = Search::getInstance();
        $SingletonSearch->clearFilter();
        $backendResults = (new BackendSearchProvider())->search((string)$this->customerNumber);
        self::assertNotEmpty($backendResults);
        self::assertSame($User->getId(), $backendResults[0]['id']);
        self::assertSame('customers', $backendResults[0]['group']);
        self::assertSame([], (new BackendSearchProvider())->search('Customer', [
            'filterGroups' => ['sites']
        ]));

        $Address->setAttribute('company', '');
        $Address->save();
        $SingletonSearch->clearFilter();
        $namedBackendResults = (new BackendSearchProvider())->search('PHPUnit Customer');
        self::assertNotEmpty($namedBackendResults);
        self::assertStringContainsString('PHPUnit Customer', $namedBackendResults[0]['title']);
        $Address->setAttribute('company', 'PHPUnit Customer GmbH');
        $Address->save();

        $Address->setAttributes([
            'company' => '',
            'firstname' => '',
            'lastname' => ''
        ]);
        $Address->save();
        $User->setAttribute('firstname', '');
        $User->setAttribute('lastname', '');
        $User->save();
        $SingletonSearch->clearFilter();
        $fallbackResults = (new BackendSearchProvider())->search((string)$this->customerNumber);
        self::assertNotEmpty($fallbackResults);
        $Address->setAttributes([
            'company' => 'PHPUnit Customer GmbH',
            'firstname' => 'PHPUnit',
            'lastname' => 'Customer'
        ]);
        $Address->save();
        $User->setAttribute('firstname', 'PHPUnit');
        $User->setAttribute('lastname', 'Customer');
        $User->save();

        $Customers->addCommentToUser($User, 'Initial PHPUnit comment');
        $comments = $Customers->getUserComments($User)->toArray();
        self::assertCount(1, $comments);
        self::assertSame('Initial PHPUnit comment', $comments[0]['message']);

        $Customers->editComment(
            $User,
            $comments[0]['id'],
            'quiqqer/customer',
            'Edited PHPUnit comment'
        );
        self::assertSame(
            'Edited PHPUnit comment',
            $Customers->getUserComments($User)->toArray()[0]['message']
        );

        $Customers->addHistoryToUser($User, 'PHPUnit history entry');
        $history = $Customers->getUserHistory($User)->toArray();
        self::assertContains('PHPUnit history entry', array_column($history, 'message'));

        $Customers->removeUserFromCustomerGroup($User->getUUID());
        $User->refresh();
        self::assertFalse($User->isInGroup($Customers->getCustomerGroupId()));

        $Customers->addUserToCustomerGroup($User->getUUID());
        $User->refresh();
        self::assertTrue($User->isInGroup($Customers->getCustomerGroupId()));

        $StandardAddress = $User->getStandardAddress();
        self::assertInstanceOf(QUI\Users\Address::class, $StandardAddress);
        $StandardAddress->addMail('standard@example.invalid');
        $StandardAddress->save();

        $ErpAddress = $User->addAddress();
        self::assertInstanceOf(QUI\Users\Address::class, $ErpAddress);
        $ErpAddress->addMail('erp@example.invalid');
        $ErpAddress->save();

        $ContactAddress = $User->addAddress();
        self::assertInstanceOf(QUI\Users\Address::class, $ContactAddress);
        $ContactAddress->addMail('contact@example.invalid');
        $ContactAddress->save();

        $User->setAttribute('quiqqer.erp.address', $ErpAddress->getUUID());
        $User->setAttribute('quiqqer.erp.customer.contact.person', $ContactAddress->getUUID());
        $User->setAttribute('quiqqer.erp.customer.payment.term', 21);
        $User->save();
        $User->refresh();

        self::assertSame($ErpAddress->getUUID(), $User->getAttribute('quiqqer.erp.address'));
        self::assertSame(
            ['erp@example.invalid'],
            $User->getAddress((string)$User->getAttribute('quiqqer.erp.address'))->getMailList()
        );

        $Utils = Utils::getInstance();
        self::assertSame('erp@example.invalid', $Utils->getEmailByCustomer($User));
        self::assertSame('contact@example.invalid', $Utils->getContactEmailByCustomer($User));
        self::assertInstanceOf(QUI\ERP\Address::class, $Utils->getContactPersonAddress($User));
        self::assertSame(21, $Utils->getPaymentTimeForUser($User->getUUID()));
        self::assertGreaterThanOrEqual(0, $Utils->getPaymentTimeForUser('missing-user'));
        self::assertSame($Customers->getCustomerGroupId(), $Utils->getCustomerGroup()?->getUUID());

        $SnapshotMethod = new ReflectionMethod(EventHandler::class, 'createUserSnapshot');
        $snapshot = $SnapshotMethod->invoke(null, $User);
        self::assertSame((string)$this->customerNumber, $snapshot['customerId']['value']);
        self::assertSame('standard@example.invalid', $snapshot['email']['value']);
        self::assertSame('Aachen', $snapshot['address.city']['value']);
        self::assertNotSame('', $snapshot['contactPerson']['display']);

        $IsCustomerMethod = new ReflectionMethod(EventHandler::class, 'isCustomerUser');
        self::assertTrue($IsCustomerMethod->invoke(null, $User));
        $TrackAddressMethod = new ReflectionMethod(EventHandler::class, 'shouldTrackAddressHistory');
        self::assertTrue($TrackAddressMethod->invoke(null, $StandardAddress, $User));
        self::assertFalse($TrackAddressMethod->invoke(null, $ErpAddress, $User));

        EventHandler::onUserSaveBegin($User);
        EventHandler::onUserSaveEnd($User);
        (new ReflectionMethod(EventHandler::class, 'rememberUserSnapshot'))->invoke(null, $User);
        $User->setAttribute('quiqqer.erp.customer.referenceCode', 'PHPUNIT-REFERENCE');
        EventHandler::onUserSaveEnd($User);
        self::assertNotEmpty($Customers->getUserHistory($User)->toArray());

        EventHandler::onUserAddressSaveBegin($StandardAddress, $User);
        $StandardAddress->setAttribute('city', 'History City');
        EventHandler::onUserAddressSave($StandardAddress, $User);
        self::assertStringContainsString(
            'History City',
            json_encode($Customers->getUserHistory($User)->toArray(), JSON_THROW_ON_ERROR)
        );

        $History = new QUI\ERP\Comments();
        EventHandler::onQuiqqerErpGetHistoryByUser($User, $History);
        self::assertNotEmpty($History->toArray());

        $extraAttributes = [];
        EventHandler::onUserExtraAttributes($User, $extraAttributes);
        self::assertIsArray($extraAttributes);
        EventHandler::onPackageSetup(QUI::getPackage('quiqqer/customer'));

        $Collector = new QUI\Smarty\Collector();
        EventHandler::onFrontendUserDataMiddle($Collector, $User, $StandardAddress);
        self::assertIsString($Collector->getContent());

        if (
            class_exists(QUI\ERP\Order\AbstractOrder::class)
            && class_exists(QUI\ERP\Order\Controls\OrderProcess\CustomerData::class)
        ) {
            $Order = $this->createMock(QUI\ERP\Order\AbstractOrder::class);
            $Order->method('getCustomer')->willReturn(QUI\ERP\User::convertUserToErpUser($User));
            (new ReflectionMethod(EventHandler::class, 'addOrderCustomerToCustomerGroup'))
                ->invoke(null, $Order);
            self::assertTrue($User->isInGroup($Customers->getCustomerGroupId()));

            $Step = $this->createMock(QUI\ERP\Order\Controls\OrderProcess\CustomerData::class);
            $Step->method('getOrder')->willReturn($Order);
            EventHandler::onQuiqqerOrderCustomerDataSaveEnd($Step);
        }

        $customerLogin = !empty(QUI::getPackage('quiqqer/customer')
            ->getConfig()?->getValue('customer', 'customerLogin'));

        try {
            EventHandler::onUserActivateBegin($User, false, null);
            self::assertTrue($customerLogin);
        } catch (QUI\Users\Exception) {
            self::assertFalse($customerLogin);
        }

        $Customers->setAttributesToCustomer($User->getUUID(), [
            'password1' => 'PHPUnit-Customer-Password-2026!',
            'password2' => 'PHPUnit-Customer-Password-2026!',
            'address-firstname' => 'Changed',
            'address-lastname' => 'Customer',
            'address-communication' => [
                ['type' => 'email', 'no' => 'changed@example.invalid'],
                ['type' => 'tel', 'no' => '+49 241 123456']
            ],
            'address-delivery-firstname' => 'Delivery',
            'address-delivery-lastname' => 'Customer',
            'address-delivery-street_no' => 'Delivery Street 5',
            'address-delivery-zip' => '10115',
            'address-delivery-city' => 'Berlin',
            'address-delivery-country' => 'DE',
            'group' => $Customers->getCustomerGroupId()
        ]);
        $User->refresh();
        self::assertSame('Changed', $User->getAttribute('firstname'));
        self::assertSame('changed@example.invalid', $User->getAttribute('email'));
        self::assertNotEmpty($User->getAttribute('quiqqer.delivery.address'));

        $Customers->setAttributesToCustomer($User->getUUID(), [
            'address-suffix' => 'Back office',
            'address-communication' => [
                ['type' => 'email', 'no' => ''],
                ['no' => '+49 241 000000']
            ],
            'address-delivery-city' => 'Hamburg',
            'groups' => $Customers->getCustomerGroupId()
        ]);
        $User->refresh();
        self::assertSame('', $User->getAttribute('email'));
        self::assertSame(
            'Hamburg',
            $User->getAddress((string)$User->getAttribute('quiqqer.delivery.address'))->getAttribute('city')
        );

        $Customers->addUserToCustomerGroup(false);
        $Customers->removeUserFromCustomerGroup(false);
        $Customers->setAttributesToCustomer(false, []);

        $User->setAttribute('comments', json_encode([
            ['message' => 'missing metadata'],
            ['id' => 'wrong-source', 'source' => 'another/package', 'message' => 'unchanged'],
            ['id' => 'wrong-id', 'source' => 'quiqqer/customer', 'message' => 'unchanged']
        ], JSON_THROW_ON_ERROR));
        $User->save();
        $Customers->editComment($User, 'target-id', 'quiqqer/customer', 'must not be used');

        try {
            $Customers->getCustomerByCustomerNo('phpunit-missing-customer');
            self::fail('An unknown customer number must not resolve to a user.');
        } catch (QUI\Exception $Exception) {
            self::assertSame(404, $Exception->getCode());
        }

        $NumberRange = new QUI\ERP\Customer\NumberRange();
        self::assertNotSame('', $NumberRange->getTitle());
        $NumberRange->setRange(999999998);
        self::assertSame(999999998, $NumberRange->getRange());

        $Config = QUI::getPackage('quiqqer/customer')->getConfig();
        self::assertInstanceOf(QUI\Config::class, $Config);
        $Config->set('customer', 'nextCustomerNo', 'not-configured');
        $Config->save();
        self::assertGreaterThan(0, $NumberRange->getRange());
        $Config->set('customer', 'nextCustomerNo', 999999999);
        $Config->save();
    }

    private function cleanupCustomerNumber(): void
    {
        $QueryBuilder = QUI::getQueryBuilder();
        $userIds = $QueryBuilder
            ->select('uuid')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI::getDBTableName('users')))
            ->where($QueryBuilder->expr()->eq(
                QUI\Utils\Doctrine::quoteIdentifier('customerId'),
                ':customerId'
            ))
            ->setParameter('customerId', $this->customerNumber)
            ->executeQuery()
            ->fetchFirstColumn();

        foreach ($userIds as $userId) {
            try {
                QUI::getUsers()->deleteUser((string)$userId);
            } catch (Throwable) {
            }
        }
    }

    private function replaceSessionUser(UserInterface $User): ?UserInterface
    {
        $Users = QUI::getUsers();
        $Property = new ReflectionProperty($Users, 'Session');
        $PreviousUser = $Property->getValue($Users);
        $Property->setValue($Users, $User);

        return $PreviousUser instanceof UserInterface ? $PreviousUser : null;
    }
}
