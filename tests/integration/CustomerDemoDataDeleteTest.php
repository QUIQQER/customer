<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Customer\Customers;
use QUI\ERP\Customer\DemoData\CustomerDemoDataCreator;
use QUI\ERP\DemoData\DTO\DemoDataReference;
use QUI\ERP\DemoData\DTO\DemoDataReferenceCollection;
use QUI\Interfaces\Users\User as UserInterface;
use ReflectionProperty;
use Throwable;

final class CustomerDemoDataDeleteTest extends TestCase
{
    private ?string $userUuid = null;
    private ?UserInterface $previousSessionUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        $Property = new ReflectionProperty(QUI::getUsers(), 'Session');
        $PreviousUser = $Property->getValue(QUI::getUsers());
        $Property->setValue(QUI::getUsers(), QUI::getUsers()->getSystemUser());
        $this->previousSessionUser = $PreviousUser instanceof UserInterface ? $PreviousUser : null;
    }

    protected function tearDown(): void
    {
        if ($this->userUuid !== null) {
            try {
                QUI::getUsers()->deleteUser($this->userUuid);
            } catch (Throwable) {
            }
        }

        if ($this->previousSessionUser !== null) {
            (new ReflectionProperty(QUI::getUsers(), 'Session'))
                ->setValue(QUI::getUsers(), $this->previousSessionUser);
        }

        parent::tearDown();
    }

    public function testCustomerReferencesDeleteEachCustomerOnce(): void
    {
        $User = QUI::getUsers()->createChildWithAttributes([
            'username' => 'phpunit-demo-delete-' . bin2hex(random_bytes(6))
        ], QUI::getUsers()->getSystemUser());
        $this->userUuid = $User->getUUID();
        $Reference = new DemoDataReference(
            'quiqqer.customer',
            'customer',
            $this->userUuid,
            'customer',
            []
        );
        $Collection = new DemoDataReferenceCollection([
            'quiqqer.customer' => [$Reference, $Reference]
        ]);

        (new CustomerDemoDataCreator(Customers::getInstance()))->deleteDemoData($Collection);

        try {
            QUI::getUsers()->get($this->userUuid);
            self::fail('The referenced demo customer must be deleted.');
        } catch (QUI\Exception $Exception) {
            self::assertSame(404, $Exception->getCode());
        }

        $this->userUuid = null;
    }
}
