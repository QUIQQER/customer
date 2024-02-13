<?php

namespace QUI\ERP\Customer\Console;

use QUI;
use QUI\ERP\Customer\Utils;
use QUI\ERP\Customer\OpenItemsList\Handler as OpenItemsListHandler;

/**
 * Updates all (or specific) open items records.
 */
class UpdateOpenItemsRecords extends QUI\System\Console\Tool
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setName('customer:update-open-items-records')
            ->setDescription(
                "Update records of open items."
            );

        $this->addArgument(
            'user_id',
            'Specific user ID. If provided, only the open items of this user will be updated.',
            false,
            true
        );
    }

    /**
     * Execute the console tool
     */
    public function execute()
    {
        $userId = $this->getArgument('user_id');

        if (!empty($userId)) {
            $userIds = [$userId];
        } else {
            $userIds = Utils::getInstance()->getCustomerGroup()->getUserIds();
        }

        $Users = QUI::getUsers();

        foreach ($userIds as $userId) {
            try {
                $User = $Users->get($userId);

                $this->writeLn("Update open items for user #" . $userId . " (" . $User->getName() . ")");
                OpenItemsListHandler::updateOpenItemsRecord($User);
                $this->writeLn("  -> SUCCESS!");
            } catch (\Exception $Exception) {
                QUI\System\Log::writeException($Exception);
                $this->writeLn("  -> ERROR: " . $Exception->getMessage());
            }
        }

        $this->exitSuccess();
    }

    /**
     * Exits the console tool with a success msg and status 0
     *
     * @return void
     */
    protected function exitSuccess(): void
    {
        $this->writeLn("\n\nBookings successfully imported.");
        $this->writeLn("");

        exit(0);
    }

    /**
     * Exits the console tool with an error msg and status 1
     *
     * @param $msg
     * @return void
     */
    protected function exitFail($msg): void
    {
        $this->writeLn("Script aborted due to an error:");
        $this->writeLn("");
        $this->writeLn($msg);
        $this->writeLn("");
        $this->writeLn("");

        exit(1);
    }
}
