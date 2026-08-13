<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QUI;
use QUI\ERP\Customer\OpenItemsList\OutputProvider;
use QUI\Interfaces\Users\User as UserInterface;
use ReflectionProperty;
use Throwable;

final class OpenItemsOutputProviderTest extends TestCase
{
    private const USERNAME_PREFIX = 'phpunit-customer-output-';

    private ?string $userUuid = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupStaleUsers();
    }

    protected function tearDown(): void
    {
        QUI::getLocale()->resetCurrent();

        if ($this->userUuid !== null) {
            try {
                QUI::getUsers()->deleteUser($this->userUuid);
            } catch (Throwable) {
            }
        }

        $this->cleanupStaleUsers();
        parent::tearDown();
    }

    public function testMailTextUsesCustomerLocaleInsteadOfSessionLocale(): void
    {
        $username = self::USERNAME_PREFIX . bin2hex(random_bytes(6));
        $User = QUI::getUsers()->createChildWithAttributes([
            'username' => $username,
            'firstname' => 'Locale',
            'lastname' => 'Customer',
            'email' => $username . '@example.invalid',
            'lang' => 'en'
        ], QUI::getUsers()->getSystemUser());
        $this->userUuid = $User->getUUID();

        QUI::getLocale()->setTemporaryCurrent('de');

        self::assertStringStartsWith(
            'List of your open items - Date:',
            OutputProvider::getMailSubject($this->userUuid)
        );
        self::assertStringContainsString(
            '<p>Dear Locale Customer,</p>',
            OutputProvider::getMailBody($this->userUuid)
        );
    }

    public function testProviderExposesCustomerOutputData(): void
    {
        $username = self::USERNAME_PREFIX . bin2hex(random_bytes(6));
        $User = QUI::getUsers()->createChildWithAttributes([
            'username' => $username,
            'firstname' => 'Output',
            'lastname' => 'Customer',
            'email' => $username . '@example.invalid',
            'customerId' => 'OUTPUT-42',
            'lang' => 'en'
        ], QUI::getUsers()->getSystemUser());
        $this->userUuid = $User->getUUID();
        $Address = $User->getStandardAddress();
        self::assertInstanceOf(QUI\Users\Address::class, $Address);
        $Address->setAttributes([
            'firstname' => 'Output',
            'lastname' => 'Customer',
            'mail' => $username . '@example.invalid'
        ]);
        $Address->save(QUI::getUsers()->getSystemUser());

        self::assertSame('OpenItemsList', OutputProvider::getEntityType());
        self::assertNotSame('', OutputProvider::getEntityTypeTitle());
        self::assertNotSame('', OutputProvider::getEntityTypeTitle($User->getLocale()));
        self::assertSame($this->userUuid, OutputProvider::getEntity($this->userUuid)->getUUID());
        self::assertSame('en', OutputProvider::getLocale($this->userUuid)->getCurrent());
        self::assertStringContainsString('OUTPUT-42', OutputProvider::getDownloadFileName($this->userUuid));
        self::assertSame($username . '@example.invalid', OutputProvider::getEmailAddress($this->userUuid));
        self::assertFalse(OutputProvider::hasDownloadPermission(
            $this->userUuid,
            QUI::getUsers()->getSystemUser()
        ));
        self::assertFalse(OutputProvider::hasDownloadPermission(
            'missing-customer',
            QUI::getUsers()->getNobody()
        ));

        $SessionProperty = new ReflectionProperty(QUI::getUsers(), 'Session');
        $previousSessionUser = $SessionProperty->getValue(QUI::getUsers());
        $SessionProperty->setValue(QUI::getUsers(), $User);

        try {
            self::assertTrue(OutputProvider::hasDownloadPermission($this->userUuid, $User));
            self::assertFalse(OutputProvider::hasDownloadPermission('missing-customer', $User));
        } finally {
            if ($previousSessionUser instanceof UserInterface) {
                $SessionProperty->setValue(QUI::getUsers(), $previousSessionUser);
            }
        }

        $templateData = OutputProvider::getTemplateData($this->userUuid);
        self::assertSame($this->userUuid, $templateData['Customer']->getUUID());
        self::assertInstanceOf(QUI\Users\Address::class, $templateData['Address']);
        self::assertSame([], $templateData['OpenItemsList']->getItems());
        self::assertNotSame('', OutputProvider::getMailSubject($this->userUuid));
        self::assertStringContainsString('Output Customer', OutputProvider::getMailBody($this->userUuid));
    }

    private function cleanupStaleUsers(): void
    {
        $QueryBuilder = QUI::getQueryBuilder();
        $userIds = $QueryBuilder
            ->select('uuid')
            ->from(QUI\Utils\Doctrine::quoteIdentifier(QUI::getDBTableName('users')))
            ->where($QueryBuilder->expr()->like('username', ':username'))
            ->setParameter('username', self::USERNAME_PREFIX . '%')
            ->executeQuery()
            ->fetchFirstColumn();

        foreach ($userIds as $userId) {
            try {
                QUI::getUsers()->deleteUser((string)$userId);
            } catch (Throwable) {
            }
        }
    }
}
