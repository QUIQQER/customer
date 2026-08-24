<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Ajax;
use QUI\ERP\Customer\Customers;
use QUI\ERP\Customer\OpenItemsList\Handler;
use QUI\ERP\Customer\OpenItemsList\Item;
use QUI\ERP\Customer\OpenItemsList\ItemsList;
use QUI\Interfaces\Users\User as UserInterface;
use ReflectionProperty;
use Throwable;

final class AjaxEndpointsTest extends TestCase
{
    private const CUSTOMER_NUMBER_MIN = 870000000;
    private const CUSTOMER_NUMBER_MAX = 879999999;

    private string $customerUuid;
    private int $customerNumber;
    /** @var list<string> */
    private array $additionalCustomerUuids = [];
    private ?UserInterface $previousSessionUser = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        QUI::$Ajax = QUI::getAjax();

        foreach (
            [
                'addToCustomer.php',
                'removeFromCustomer.php',
                'getBusinessType.php',
                'getCustomerGroupId.php',
                'getCustomersData.php',
                'search.php',
                'userDisplayName.php',
                'create/getCategories.php',
                'create/createCustomer.php',
                'create/getNewCustomerNo.php',
                'create/getPrefix.php',
                'create/validateCustomerNo.php',
                'customer/addAddress.php',
                'customer/addComment.php',
                'customer/editComment.php',
                'customer/getAddress.php',
                'customer/getCategories.php',
                'customer/getCategory.php',
                'customer/getComment.php',
                'customer/getComments.php',
                'customer/getCommentsAndHistory.php',
                'customer/getCustomerLoginFlag.php',
                'customer/getHistory.php',
                'customer/getPagination.php',
                'customer/getTaxByUser.php',
                'customer/checkCalculation.php',
                'customer/instantSave.php',
                'customer/save.php',
                'OpenItemsList/getUserOpenItems.php',
                'OpenItemsList/search.php'
            ] as $file
        ) {
            require_once dirname(__DIR__, 2) . '/ajax/backend/' . $file;
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->previousSessionUser = $this->replaceSessionUser(QUI::getUsers()->getSystemUser());
        $this->customerNumber = random_int(self::CUSTOMER_NUMBER_MIN, self::CUSTOMER_NUMBER_MAX);
        $this->cleanupCustomerNumber();

        $User = Customers::getInstance()->createCustomer($this->customerNumber, [
            'firstname' => 'Ajax',
            'lastname' => 'Customer',
            'company' => 'Ajax Customer GmbH',
            'street_no' => 'Endpoint Street 1',
            'zip' => '52062',
            'city' => 'Aachen',
            'country' => 'DE'
        ]);
        $this->customerUuid = $User->getUUID();
    }

    protected function tearDown(): void
    {
        QUI::getDataBaseConnection()->delete(Handler::getTable(), [
            'userId' => $this->customerUuid
        ]);

        try {
            QUI::getUsers()->deleteUser($this->customerUuid);
        } catch (Throwable) {
        }

        foreach ($this->additionalCustomerUuids as $customerUuid) {
            try {
                QUI::getUsers()->deleteUser($customerUuid);
            } catch (Throwable) {
            }
        }

        $this->cleanupCustomerNumber();
        QUI\Cache\Manager::clear('quiqqer/customer/openitems/' . $this->customerUuid);

        if ($this->previousSessionUser !== null) {
            $this->replaceSessionUser($this->previousSessionUser);
        }

        parent::tearDown();
    }

    public function testReadEndpointsReturnCustomerData(): void
    {
        self::assertSame(
            Customers::getInstance()->getCustomerGroupId(),
            $this->callable('getCustomerGroupId')()
        );
        self::assertSame(
            (new QUI\ERP\Customer\NumberRange())->getCustomerNoPrefix(),
            $this->callable('create_getPrefix')()
        );
        self::assertSame(
            (new QUI\ERP\Customer\NumberRange())->getNextCustomerNo(),
            $this->callable('create_getNewCustomerNo')()
        );
        self::assertSame(
            Customers::getInstance()->getCustomerLoginFlag(),
            $this->callable('customer_getCustomerLoginFlag')()
        );
        self::assertCount(2, $this->callable('create_getCategories')());

        $data = $this->callable('getCustomersData')(json_encode([$this->customerUuid, 'missing-user']));
        self::assertCount(1, $data);
        self::assertSame($this->customerUuid, $data[0]['id']);
        self::assertSame((string)$this->customerNumber, $data[0]['username']);

        self::assertSame(
            QUI::getUsers()->get($this->customerUuid)->getName(),
            $this->callable('userDisplayName')($this->customerUuid, false)
        );
        self::assertStringContainsString(
            (string)$this->customerNumber,
            $this->callable('userDisplayName')($this->customerUuid, true)
        );

        $address = $this->callable('customer_getAddress')($this->customerUuid);
        self::assertSame('Ajax Customer GmbH', $address['company']);
        self::assertSame('Aachen', $address['city']);
        self::assertNotEmpty($address['uuid']);

        self::assertNotSame('', $this->callable('getBusinessType')());

        $calculation = $this->callable('customer_checkCalculation')($this->customerUuid);
        self::assertArrayHasKey('status', $calculation);
        self::assertSame('Ajax Customer GmbH', $calculation['address']['company']);
        self::assertTrue($calculation['isCompany']);

        $tax = $this->callable('customer_getTaxByUser')($this->customerUuid);

        if ($tax !== null) {
            self::assertIsArray($tax);
            self::assertArrayHasKey('vat', $tax);
        }

        self::assertNull($this->callable('customer_getTaxByUser')('missing-user'));
    }

    public function testCustomerPanelEndpointsReturnInstalledCategories(): void
    {
        $panel = $this->callable('customer_getCategories')();
        self::assertArrayHasKey('categories', $panel);
        self::assertIsArray($panel['categories']);

        if ($panel['categories'] === []) {
            return;
        }

        $category = $panel['categories'][0];
        self::assertArrayHasKey('name', $category);
        self::assertIsString($this->callable('customer_getCategory')($category['name']));
    }

    public function testCreateCustomerEndpointPersistsAddressAndAttributes(): void
    {
        $customerNumber = random_int(860000000, 869999998);
        $uuid = $this->callable('create_createCustomer')(
            (string)$customerNumber,
            json_encode([
                'firstname' => 'Created',
                'lastname' => 'By Ajax',
                'company' => '',
                'street_no' => 'Created Street 2',
                'zip' => '10115',
                'city' => 'Berlin',
                'country' => 'DE'
            ]),
            '[]',
            json_encode(['email' => 'created-by-ajax@example.invalid'])
        );
        $this->additionalCustomerUuids[] = $uuid;

        $User = QUI::getUsers()->get($uuid);
        self::assertSame($customerNumber, $User->getAttribute('customerId'));
        self::assertSame('created-by-ajax@example.invalid', $User->getAttribute('email'));
        self::assertSame('Berlin', $User->getStandardAddress()?->getAttribute('city'));
    }

    public function testSearchAndValidationEndpointsUsePersistedCustomer(): void
    {
        $result = $this->callable('search')(json_encode([
            'search' => (string)$this->customerNumber,
            'onlyCustomer' => true,
            'sortOn' => 'username',
            'sortBy' => 'ASC',
            'page' => 1,
            'perPage' => 10
        ]));
        self::assertGreaterThanOrEqual(1, $result['total']);
        self::assertContains($this->customerUuid, array_column($result['data'], 'user_uuid'));

        self::assertNull($this->callable('create_validateCustomerNo')('869999999'));

        $this->expectException(QUI\ERP\Customer\Exception::class);
        $this->callable('create_validateCustomerNo')((string)$this->customerNumber);
    }

    public function testMembershipAndAddressEndpointsMutateCustomer(): void
    {
        self::assertSame($this->customerUuid, $this->callable('removeFromCustomer')($this->customerUuid));
        $User = QUI::getUsers()->get($this->customerUuid);
        self::assertFalse($User->isInGroup(Customers::getInstance()->getCustomerGroupId()));

        self::assertSame($this->customerUuid, $this->callable('addToCustomer')($this->customerUuid));
        $User->refresh();
        self::assertTrue($User->isInGroup(Customers::getInstance()->getCustomerGroupId()));

        $addressUuid = $this->callable('customer_addAddress')($this->customerUuid);
        self::assertNotSame('', $addressUuid);
        self::assertSame($addressUuid, $User->getAddress($addressUuid)->getUUID());
    }

    public function testCommentAndHistoryEndpointsSupportPaginationAndEditing(): void
    {
        $comments = $this->callable('customer_addComment')($this->customerUuid, "First\ncomment");
        self::assertCount(1, $comments);
        $commentId = $comments[0]['id'];

        self::assertSame(
            "First\ncomment",
            $this->callable('customer_getComment')(
                $this->customerUuid,
                $commentId,
                'quiqqer/customer'
            )
        );

        $edited = $this->callable('customer_editComment')(
            $this->customerUuid,
            $commentId,
            'quiqqer/customer',
            'Edited comment'
        );
        self::assertSame('Edited comment', $edited[0]['message']);

        $paged = $this->callable('customer_getComments')($this->customerUuid, 1, 1);
        self::assertCount(1, $paged);
        self::assertSame('Edited comment', $paged[0]['message']);
        self::assertNotEmpty($this->callable('customer_getHistory')($this->customerUuid, 1, 10));
        self::assertNotEmpty($this->callable('customer_getCommentsAndHistory')($this->customerUuid, 1, 10));
        self::assertIsString($this->callable('customer_getPagination')($this->customerUuid));
    }

    public function testSaveEndpointsPersistEditableFields(): void
    {
        $this->callable('customer_instantSave')($this->customerUuid, json_encode([
            'firstname' => 'Instant',
            'lastname' => 'Saved',
            'email' => 'instant-saved@example.invalid'
        ]));
        $User = QUI::getUsers()->get($this->customerUuid);
        self::assertSame('Instant', $User->getAttribute('firstname'));
        self::assertSame('Saved', $User->getAttribute('lastname'));
        self::assertSame('instant-saved@example.invalid', $User->getAttribute('email'));

        $this->callable('customer_save')($this->customerUuid, json_encode([
            'address-firstname' => 'Full',
            'address-lastname' => 'Save',
            'address-city' => 'Berlin'
        ]));
        $User->refresh();
        self::assertSame('Full', $User->getAttribute('firstname'));
        self::assertSame('Save', $User->getAttribute('lastname'));
        self::assertSame('Berlin', $User->getStandardAddress()?->getAttribute('city'));
    }

    public function testOpenItemsEndpointsReturnCachedItemsAndDatabaseGrid(): void
    {
        $Currency = QUI\ERP\Currency\Handler::getDefaultCurrency();
        self::assertInstanceOf(QUI\ERP\Currency\Currency::class, $Currency);
        $liveResult = $this->callable('OpenItemsList_getUserOpenItems')(
            $this->customerUuid,
            json_encode([
                'sortOn' => 'date',
                'sortBy' => 'ASC',
                'page' => 1,
                'perPage' => 1
            ]),
            true
        );
        self::assertSame(0, $liveResult['total']);
        self::assertSame([], $liveResult['data']);

        $List = new ItemsList();
        $List->addItem($this->openItem($Currency, 1, 'INV-1', '2026-08-10', 100));
        Handler::syncOpenItemsRecord(QUI::getUsers()->get($this->customerUuid), $List);

        $grid = $this->callable('OpenItemsList_search')(json_encode([
            'userId' => $this->customerUuid,
            'currency' => $Currency->getCode(),
            'page' => 1,
            'perPage' => 10
        ]));
        self::assertArrayHasKey('grid', $grid);
        self::assertArrayHasKey('totals', $grid);
    }

    private function openItem(
        QUI\ERP\Currency\Currency $Currency,
        int $id,
        string $documentNo,
        string $date,
        float $amount
    ): Item {
        $Item = new Item($id, Handler::DOCUMENT_TYPE_INVOICE);
        $Item->setDocumentNo($documentNo);
        $Item->setDate(new \DateTime($date));
        $Item->setDueDate(new \DateTime($date . ' +10 days'));
        $Item->setCurrency($Currency);
        $Item->setAmountTotalNet($amount);
        $Item->setAmountTotalVat($amount * 0.19);
        $Item->setAmountTotalSum($amount * 1.19);
        $Item->setAmountPaid($amount / 2);
        $Item->setAmountOpen($amount / 2);
        $Item->setDunningLevel($id);
        $Item->setDaysDue($id);
        $Item->setHash('hash-' . $id);

        return $Item;
    }

    private function callable(string $suffix): callable
    {
        $name = 'package_quiqqer_customer_ajax_backend_' . $suffix;
        $callables = Ajax::getRegisteredCallables();
        self::assertArrayHasKey($name, $callables);

        return $callables[$name]['callable'];
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
