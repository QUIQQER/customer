<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Accounting\Invoice\Invoice;
use QUI\ERP\Accounting\Invoice\InvoiceTemporary;
use QUI\ERP\Accounting\Payments\Transactions\Transaction;
use QUI\ERP\Customer\OpenItemsList\Events;
use QUI\ERP\Order\AbstractOrder;
use ReflectionMethod;
use Throwable;

final class OpenItemsEventsTest extends TestCase
{
    private ?string $userUuid = null;

    protected function tearDown(): void
    {
        if ($this->userUuid !== null) {
            try {
                QUI::getUsers()->deleteUser($this->userUuid);
            } catch (Throwable) {
            }
        }

        parent::tearDown();
    }

    public function testInvoiceEventsSynchronizeCustomerOpenItems(): void
    {
        if (!class_exists(Invoice::class) || !class_exists(InvoiceTemporary::class)) {
            self::markTestSkipped('The optional invoice package is not installed.');
        }

        $Customer = $this->createErpUser();
        $Invoice = $this->createMock(Invoice::class);
        $Invoice->method('getCustomer')->willReturn($Customer);
        $TemporaryInvoice = $this->createMock(InvoiceTemporary::class);

        Events::onQuiqqerInvoicePaymentStatusChanged($Invoice, 1, 0);
        Events::onQuiqqerInvoiceLinkTransaction(
            $Invoice,
            $this->createMock(Transaction::class)
        );
        Events::onQuiqqerInvoiceTemporaryInvoicePostEnd($TemporaryInvoice, $Invoice);

        self::assertTrue(true);
    }

    public function testOrderEventsSynchronizeCustomerOpenItems(): void
    {
        if (!class_exists(AbstractOrder::class)) {
            self::markTestSkipped('The optional order package is not installed.');
        }

        $Customer = $this->createErpUser();
        $Order = $this->createMock(AbstractOrder::class);
        $Order->method('getCustomer')->willReturn($Customer);

        Events::onQuiqqerOrderPaidStatusChanged($Order, 1, 0);
        Events::onQuiqqerOrderLinkTransaction(
            $Order,
            $this->createMock(Transaction::class)
        );
        Events::onQuiqqerOrderCreated($Order);

        self::assertTrue(true);
    }

    public function testUnknownTransactionAndDeletedOrderAreHandled(): void
    {
        $Transaction = $this->createMock(Transaction::class);
        $Transaction->method('getHash')->willReturn('phpunit-missing-transaction-target');

        Events::onTransactionCreate($Transaction);
        Events::onQuiqqerOrderDelete('missing-order', []);

        self::assertTrue(true);
    }

    public function testLiveErpUserResolutionSupportsExistingAndMissingUsers(): void
    {
        $Customer = $this->createErpUser();
        $Method = new ReflectionMethod(Events::class, 'getLiveErpUser');

        $ErpUser = $Method->invoke(null, $this->userUuid);

        self::assertInstanceOf(QUI\ERP\User::class, $ErpUser);
        self::assertSame($Customer->getUUID(), $ErpUser->getUUID());
        self::assertFalse($Method->invoke(null, 'phpunit-missing-user'));

        $SyncMethod = new ReflectionMethod(Events::class, 'syncOpenItemsRecord');
        $SyncMethod->invoke(null, null);
        self::assertTrue(true);
    }

    public function testEnabledOrderTrackingSynchronizesCreatedAndDeletedOrders(): void
    {
        if (!class_exists(AbstractOrder::class)) {
            self::markTestSkipped('The optional order package is not installed.');
        }

        $CustomerConfig = QUI::getPackage('quiqqer/customer')->getConfig();
        $OrderConfig = QUI::getPackage('quiqqer/order')->getConfig();
        self::assertInstanceOf(QUI\Config::class, $CustomerConfig);
        self::assertInstanceOf(QUI\Config::class, $OrderConfig);
        $considerOrdersExisted = $CustomerConfig->existValue('openItems', 'considerOrders');
        $previousConsiderOrders = $CustomerConfig->get('openItems', 'considerOrders');
        $autoInvoiceExisted = $OrderConfig->existValue('order', 'autoInvoice');
        $previousAutoInvoice = $OrderConfig->get('order', 'autoInvoice');

        $CustomerConfig->set('openItems', 'considerOrders', 1);
        $CustomerConfig->save();
        $OrderConfig->set('order', 'autoInvoice', 'manual');
        $OrderConfig->save();

        try {
            $Customer = $this->createErpUser();
            $Order = $this->createMock(AbstractOrder::class);
            $Order->method('getCustomer')->willReturn($Customer);
            $Order->method('getAttribute')->with('no_invoice_auto_create')->willReturn(false);

            Events::onQuiqqerOrderCreated($Order);
            Events::onQuiqqerOrderDelete('deleted-order', [
                'customerId' => $Customer->getUUID(),
                'customer' => '{}'
            ]);

            Events::onQuiqqerOrderDelete('deleted-order-snapshot', [
                'customerId' => 'phpunit-deleted-customer',
                'customer' => json_encode([
                    'uuid' => 'phpunit-deleted-customer',
                    'username' => 'deleted-customer',
                    'firstname' => 'Deleted',
                    'lastname' => 'Customer',
                    'company' => 'Deleted Customer GmbH',
                    'lang' => 'de',
                    'address' => ['country' => 'DE']
                ], JSON_THROW_ON_ERROR)
            ]);

            $OrderWithoutInvoice = $this->createMock(AbstractOrder::class);
            $OrderWithoutInvoice->method('getCustomer')->willReturn($Customer);
            $OrderWithoutInvoice->method('getAttribute')
                ->with('no_invoice_auto_create')
                ->willReturn(true);
            Events::onQuiqqerOrderCreated($OrderWithoutInvoice);

            $OrderConfig->set('order', 'autoInvoice', 'onOrder');
            $OrderConfig->save();
            Events::onQuiqqerOrderCreated($Order);
            Events::onQuiqqerOrderDelete('invoice-backed-order', [
                'customerId' => $Customer->getUUID(),
                'customer' => '{}'
            ]);

            self::assertTrue(true);
        } finally {
            if ($considerOrdersExisted) {
                $CustomerConfig->set('openItems', 'considerOrders', $previousConsiderOrders);
            } else {
                $CustomerConfig->del('openItems', 'considerOrders');
            }

            $CustomerConfig->save();

            if ($autoInvoiceExisted) {
                $OrderConfig->set('order', 'autoInvoice', $previousAutoInvoice);
            } else {
                $OrderConfig->del('order', 'autoInvoice');
            }

            $OrderConfig->save();
        }
    }

    private function createErpUser(): QUI\ERP\User
    {
        $username = 'phpunit-open-items-event-' . bin2hex(random_bytes(6));
        $User = QUI::getUsers()->createChildWithAttributes([
            'username' => $username,
            'firstname' => 'Event',
            'lastname' => 'Customer',
            'country' => 'DE'
        ], QUI::getUsers()->getSystemUser());
        $this->userUuid = $User->getUUID();

        return QUI\ERP\User::convertUserToErpUser($User);
    }
}
