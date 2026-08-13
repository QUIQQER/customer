<?php

declare(strict_types=1);

namespace QUI\ERP\Customer\Tests\Integration;

use PHPUnit\Framework\TestCase;
use QUI;
use Throwable;

require_once dirname(__DIR__) . '/support/TestableUpdateOpenItemsRecords.php';

final class UpdateOpenItemsRecordsTest extends TestCase
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

    public function testSpecificExistingAndMissingUsersAreProcessed(): void
    {
        $User = QUI::getUsers()->createChildWithAttributes([
            'username' => 'phpunit-update-open-items-' . bin2hex(random_bytes(6)),
            'firstname' => 'Console',
            'lastname' => 'Customer'
        ], QUI::getUsers()->getSystemUser());
        $this->userUuid = $User->getUUID();

        $Tool = new TestableUpdateOpenItemsRecords();
        $Tool->setArgument('user_id', $this->userUuid);
        $Tool->execute();

        self::assertTrue($Tool->success);
        self::assertStringContainsString('SUCCESS', implode("\n", $Tool->output));

        $MissingUserTool = new TestableUpdateOpenItemsRecords();
        $MissingUserTool->setArgument('user_id', 'phpunit-missing-user');
        $MissingUserTool->execute();

        self::assertTrue($MissingUserTool->success);
        self::assertStringContainsString('ERROR', implode("\n", $MissingUserTool->output));
    }
}
