<?php

declare(strict_types=1);

namespace QUI\ERP\Customer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use QUI;
use ReflectionMethod;

final class EventHandlerTest extends TestCase
{
    public function testAttributeXmlIsParsedWithEncryptionMetadata(): void
    {
        self::assertSame([
            ['name' => 'customer.secret', 'encrypt' => true],
            ['name' => 'customer.reference', 'encrypt' => false]
        ], $this->invoke('readAttributesFromUserXML', [
            dirname(__DIR__, 4) . '/fixtures/customer-attributes.xml'
        ]));
    }

    #[DataProvider('snapshotValueProvider')]
    public function testSnapshotValuesAreNormalized(mixed $value, string $expected): void
    {
        self::assertSame($expected, $this->invoke('normalizeSnapshotValue', [$value]));
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function snapshotValueProvider(): array
    {
        return [
            'null' => [null, ''],
            'false' => [false, ''],
            'trimmed string' => [' value ', 'value'],
            'integer' => [42, '42'],
            'array' => [['key' => 'value'], '{"key":"value"}']
        ];
    }

    public function testSnapshotEntryAndDiffRetainDisplayValues(): void
    {
        self::assertSame(
            ['value' => '42', 'display' => 'Customer 42'],
            $this->invoke('createSnapshotEntry', [42, 'Customer 42'])
        );

        $changes = $this->invoke('diffUserSnapshots', [[
            'email' => ['value' => 'old@example.invalid', 'display' => 'old@example.invalid'],
            'unchanged' => ['value' => 'same', 'display' => 'same'],
            'removed' => ['value' => 'removed', 'display' => 'removed']
        ], [
            'email' => ['value' => 'new@example.invalid', 'display' => 'new@example.invalid'],
            'unchanged' => ['value' => 'same', 'display' => 'same']
        ]]);

        self::assertSame([[
            'field' => 'email',
            'old' => ['value' => 'old@example.invalid', 'display' => 'old@example.invalid'],
            'new' => ['value' => 'new@example.invalid', 'display' => 'new@example.invalid']
        ]], $changes);
        self::assertStringContainsString(
            'old@example.invalid',
            $this->invoke('createHistoryMessage', [$changes[0]])
        );
    }

    public function testAddressSnapshotNormalizesAttributesAndPhones(): void
    {
        $Address = $this->createMock(QUI\Users\Address::class);
        $Address->method('getAttribute')->willReturnMap([
            ['salutation', 'mr'],
            ['firstname', 'Max'],
            ['lastname', 'Mustermann'],
            ['company', 'Example GmbH'],
            ['street_no', 'Street 1'],
            ['zip', '52062'],
            ['city', 'Aachen'],
            ['country', 'DE'],
            ['suffix', 'Office']
        ]);
        $Address->method('getPhone')->willReturn('+49 241 1');
        $Address->method('getMobile')->willReturn('+49 170 1');
        $Address->method('getFax')->willReturn('+49 241 2');

        $snapshot = $this->invoke('createAddressSnapshot', [$Address]);

        self::assertSame('Max', $snapshot['address.firstname']['value']);
        self::assertSame('Aachen', $snapshot['address.city']['value']);
        self::assertNotSame('', $snapshot['address.country']['display']);
        self::assertSame('+49 241 1', $snapshot['address.phone.tel']['value']);
        self::assertSame('+49 170 1', $snapshot['address.phone.mobile']['value']);
        self::assertSame('+49 241 2', $snapshot['address.phone.fax']['value']);
        self::assertSame('', $this->invoke('getAddressAttribute', [null, 'city']));
        self::assertSame('', $this->invoke('getAddressPhone', [null, 'tel']));
    }

    public function testGroupAndCountryDisplaysFallBackToIdentifiers(): void
    {
        $customerGroupId = Customers::getInstance()->getCustomerGroupId();

        self::assertNotSame('', $this->invoke('getGroupDisplay', [$customerGroupId]));
        self::assertSame('missing-group', $this->invoke('getGroupDisplay', ['missing-group']));
        self::assertSame('', $this->invoke('getGroupDisplay', ['']));
        self::assertStringContainsString(
            'missing-group',
            $this->invoke('getGroupsDisplay', [[$customerGroupId, 'missing-group']])
        );
        self::assertNotSame('', $this->invoke('getCountryDisplay', ['DE']));
        self::assertSame('XX', $this->invoke('getCountryDisplay', ['XX']));
        self::assertSame('', $this->invoke('getCountryDisplay', ['']));
    }

    public function testCustomerDetectionUsesMainGroupAndRejectsUnrelatedUsers(): void
    {
        $customerGroupId = Customers::getInstance()->getCustomerGroupId();
        $MainGroupCustomer = $this->createMock(QUI\Users\User::class);
        $MainGroupCustomer->method('getAttribute')->willReturnMap([
            ['customerId', false],
            ['mainGroup', $customerGroupId]
        ]);
        self::assertTrue($this->invoke('isCustomerUser', [$MainGroupCustomer]));

        $UnrelatedUser = $this->createMock(QUI\Users\User::class);
        $UnrelatedUser->method('getAttribute')->willReturn(false);
        $UnrelatedUser->method('isInGroup')->willReturn(false);
        self::assertFalse($this->invoke('isCustomerUser', [$UnrelatedUser]));

        $Address = $this->createMock(QUI\Users\Address::class);
        self::assertFalse($this->invoke('shouldTrackAddressHistory', [$Address, $UnrelatedUser]));
    }

    public function testContactDisplayAndEmptyHistoryValuesFallBackSafely(): void
    {
        $User = $this->createMock(QUI\Users\User::class);
        $User->method('getAddress')->willThrowException(new QUI\Exception('missing'));

        self::assertSame('', $this->invoke('getContactPersonDisplay', [$User, '']));
        self::assertSame(
            'missing-address',
            $this->invoke('getContactPersonDisplay', [$User, 'missing-address'])
        );

        $message = $this->invoke('createHistoryMessage', [[
            'field' => ['invalid'],
            'old' => ['value' => '', 'display' => ''],
            'new' => ['value' => '', 'display' => '']
        ]]);
        self::assertIsString($message);
        self::assertNotSame('', $message);
    }

    public function testPublicEventsIgnoreUnrelatedOrUnsupportedObjects(): void
    {
        $Package = $this->createMock(QUI\Package\Package::class);
        $Package->method('getName')->willReturn('quiqqer/core');
        EventHandler::onPackageSetup($Package);

        $User = $this->createMock(QUI\Interfaces\Users\User::class);
        $attributes = [['name' => 'existing', 'encrypt' => false]];
        EventHandler::onUserSaveBegin($User);
        EventHandler::onUserSaveEnd($User);

        $Address = $this->createMock(QUI\Users\Address::class);
        EventHandler::onUserAddressSaveBegin($Address, $User);
        EventHandler::onUserAddressSave($Address, $User);
        EventHandler::onAdminLoadFooter();

        $CoreUser = $this->createMock(QUI\Users\User::class);
        $CoreUser->method('isInGroup')->willReturn(false);
        EventHandler::onUserActivateBegin($CoreUser, false, null);
        $AddressWithoutUuid = $this->createMock(QUI\Users\Address::class);
        $AddressWithoutUuid->method('getUUID')->willReturn(null);
        EventHandler::onUserAddressSave($AddressWithoutUuid, $CoreUser);

        self::assertSame([['name' => 'existing', 'encrypt' => false]], $attributes);
    }

    private function invoke(string $method, array $arguments): mixed
    {
        return (new ReflectionMethod(EventHandler::class, $method))->invoke(null, ...$arguments);
    }
}
