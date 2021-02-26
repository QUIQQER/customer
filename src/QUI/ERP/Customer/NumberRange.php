<?php

namespace QUI\ERP\Customer;

use QUI;
use QUI\ERP\Api\NumberRangeInterface;

/**
 * Class NumberRange
 *
 * Number range for customer no.
 */
class NumberRange implements NumberRangeInterface
{
    /**
     * @param null|QUI\Locale $Locale
     *
     * @return string
     */
    public function getTitle($Locale = null)
    {
        if ($Locale === null) {
            $Locale = QUI::getLocale();
        }

        return $Locale->get('quiqqer/customer', 'NumberRange.title');
    }

    /**
     * Return the current start range value
     *
     * The process here seems a bit weird, but let me explain:
     *
     * If the next customer no. is set via config / backend settings, then it should be used
     * automatically for the next customer that is created.
     *
     * However, a customer can also be created with a different (i.e. higher) customer no. That
     * is because the column `customerId` in the `users` table is not an AUTO_INCREMENT column.
     *
     * This is the reason why the next customer no. from the config (set by an admin) and the actual
     * next customer no. from the database have to be compared.
     *
     * Whichever number is HIGHER has to be used as the next customer no. (+1).
     *
     * @return int|string
     */
    public function getRange()
    {
        // Get from config
        $nextCustomerNoFromConfig = false;
        $nextCustomerNoFromDb     = false;

        try {
            $Conf                     = QUI::getPackage('quiqqer/customer')->getConfig();
            $nextCustomerNoFromConfig = $Conf->get('customer', 'nextCustomerNo');

            if (!empty($nextCustomerNoFromConfig)) {
                $nextCustomerNoFromConfig = (int)$nextCustomerNoFromConfig;
            } else {
                $nextCustomerNoFromConfig = false;
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        // Determine from current customer numbers in user table
        $tbl    = QUI::getUsers()::table();
        $sql    = "SELECT * FROM $tbl WHERE `customerId` REGEXP '.*[0-9]+$' ORDER BY cast(`customerId` as UNSIGNED) DESC LIMIT 1";
        $result = QUI::getDataBase()->fetchSQL($sql);

        if (!empty($result)) {
            \preg_match_all('#([^\d]*)([0-9]+)#', $result[0]['customerId'], $matches);
            $nextCustomerNoFromDb = (int)$matches[2][0] + 1;
        }

        if (empty($nextCustomerNoFromConfig) && empty($nextCustomerNoFromDb)) {
            return 1;
        }

        if (empty($nextCustomerNoFromConfig)) {
            return $nextCustomerNoFromDb;
        }

        if (empty($nextCustomerNoFromDb)) {
            return $nextCustomerNoFromConfig;
        }

        return $nextCustomerNoFromConfig > $nextCustomerNoFromDb ?
            $nextCustomerNoFromConfig :
            $nextCustomerNoFromDb;
    }

    /**
     * @param int $range
     * @return void
     */
    public function setRange($range)
    {
        try {
            $Conf = QUI::getPackage('quiqqer/customer')->getConfig();
            $Conf->set('customer', 'nextCustomerNo', $range);
            $Conf->save();
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }
    }

    /**
     * Get the next customer no.
     *
     * @return int|string
     */
    public function getNextCustomerNo()
    {
        $prefix        = $this->getCustomerNoPrefix();
        $newCustomerNo = $this->getRange();
        $usersTbl      = QUI::getUsers()::table();

        // Check if exists
        $result = QUI::getDataBase()->fetch([
            'select' => ['id'],
            'from'   => $usersTbl,
            'where'  => [
                'customerId' => $prefix.$newCustomerNo
            ]
        ]);

        if (empty($result)) {
            return $prefix.$newCustomerNo;
        }

        // If customer already existed -> check customer Id of latest user
        $sql    = "SELECT `customerId` FROM $usersTbl WHERE `customerId` IS NOT NULL AND `customerId` != '' ORDER BY `id` DESC LIMIT 1";
        $result = QUI::getDataBase()->fetchSQL($sql);

        \preg_match_all('#([^\d]*)([0-9]+)#', $result[0]['customerId'], $matches);
        $nextCustomerNoFromDb = (int)$matches[2][0] + 1;

        $this->setRange($nextCustomerNoFromDb);

        return $prefix.$nextCustomerNoFromDb;
    }

    /**
     * Get customer no. prefix
     *
     * @return string
     */
    protected function getCustomerNoPrefix(): string
    {
        try {
            $Conf   = QUI::getPackage('quiqqer/customer')->getConfig();
            $prefix = $Conf->get('customer', 'customerNoPrefix');

            if (!empty($prefix)) {
                return $prefix;
            }
        } catch (\Exception $Exception) {
            QUI\System\Log::writeException($Exception);
        }

        return '';
    }
}
